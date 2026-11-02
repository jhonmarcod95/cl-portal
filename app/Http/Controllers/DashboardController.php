<?php

namespace App\Http\Controllers;

use App\Models\ClLog;
use App\Models\ClMessage;
use App\Models\Company;
use App\Models\CreditLimit;
use App\Models\CreditLimitRemark;
use App\Models\Remark;
use App\Models\SapLog;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DashboardController extends Controller
{
    //
    public function index(Request $request)
    {
        return Inertia::render('Index');
    }

    public function cl_data($from, $to)
    {
        $user_companies = Auth::user()->company()->pluck('description')->toArray(); // use to filter company based on users
        $user_plants = auth()->user()->plants->pluck('plant_code');

        $credit_limits = CreditLimit::with('clStatus')
            ->when(count($user_plants), function ($q) use ($user_plants) {
                $q->whereIn('plant_code', $user_plants);
            })
            ->whereBetween('date_issue', [$from, $to])
            ->whereIn('company', $user_companies)
            ->whereHas('clStatus')
            ->where(function ($query) {
                $query->where('request_date', '>=', DB::raw('DATE(pushed_at)'))
                    ->orWhere('company', '!=', 'ALC')
                    ->orWhere('status', '!=', 0);
            })
            ->orderByDesc('version') // sort by latest version
            ->get();

        $credit_limits = $this->getLatestCreditLimit($credit_limits);

        $approved = $credit_limits->where('status', '1')->values();
        $rejected = $credit_limits->where('status', '2')->values();
        $forApproval = $credit_limits->where('status', '0')->values();

        // past 7days for approval
        $yesterday = Carbon::now()->addDays(-1)->format('Y-m-d');
        $pastSevenDays = Carbon::now()->addDays(-8)->format('Y-m-d');

        $pastForApproval = CreditLimit::with('clStatus')
            ->when(count($user_plants), function ($q) use ($user_plants) {
                $q->whereIn('plant_code', $user_plants);
            })
            ->whereBetween('date_issue', [$pastSevenDays, $yesterday])
            ->whereIn('company', $user_companies)
            ->where('status', '0')
            ->whereHas('clStatus')
            ->where(function ($query) {
                $query->where('request_date', '>=', DB::raw('DATE(pushed_at)'))
                    ->orWhere('company', '!=', 'ALC')
                    ->orWhere('status', '!=', 0);
            })
            ->orderByDesc('version')
            ->get();

        $pastForApproval = $this->getLatestCreditLimit($pastForApproval);

        return [
            'approved' => $approved,
            'rejected' => $rejected,
            'for_approval' => $forApproval,
            'past_for_approval' => $pastForApproval,
            'remarks_count' => [],
            'remarks_filter' => [],
            'review_cafs' => [],
        ];
    }

    private function getLatestCreditLimit($credit_limits)
    {
        // group By SO and Company to Get unique SO
        $credit_limits = $credit_limits->groupBy(function ($item) {
            return $item->so_number.$item->company;
        });

        // get latest CLs
        $latest_cls = [];
        foreach ($credit_limits as $credit_limit) {
            $latest_cl = $credit_limit[0];
            $latest_cl['description'] = $credit_limit[0]['clStatus']['description'];
            $latest_cl['is_samples'] = (in_array($latest_cl['order_type'], ['ZSAM', 'ZASM', 'ZSAO'])) ? true : false;

            $latest_cls[] = $latest_cl;
        }
        $latest_cls = collect($latest_cls)->sortBy(function ($item) {
            return $item->type.$item->pushed_at;
        })->values();

        return $latest_cls;
    }

    public function cl_approve(Request $request)
    {

        $so_id = $request->id;
        $remarks = $request->remarks;
        $checkedRemarks = $request->checkedRemarks;
        $so_number = $request->so_number;
        $company = $request->company;
        $sap_server = $request->sap_server;
        $status = '1';

        $checkedRemarks[] = $remarks;
        if (count($checkedRemarks) == 1 && $remarks == null) {

        } else {
            $checkedRemarks = array_filter($checkedRemarks, function ($value) {
                return $value !== null;
            });
        }

        DB::beginTransaction();

        // update cl status
        CreditLimit::where('so_number', $so_number)
            ->update([
                'status' => $status,
                'updated_by' => Auth::user()->id,
            ]);

        // add remarks
        foreach ($checkedRemarks as $checkedRemark) {

            /* remarks id and description ****** */
            $remarks_id = '0';
            if (Remark::where('id', $checkedRemark)->exists()) {
                $remarks = Remark::where('id', $checkedRemark)->first();
                $remarks_id = $remarks->id;
                $remarks_desc = $remarks->description;
            } else {
                $remarks_desc = $checkedRemark;
            }
            /* ********************************* */

            $credit_limit_remark = new CreditLimitRemark();
            $credit_limit_remark->so_number = $so_number;
            $credit_limit_remark->remarks_id = $remarks_id;
            $credit_limit_remark->description = $remarks_desc;
            $credit_limit_remark->updated_by = Auth::user()->id;
            $credit_limit_remark->status = $status;
            $credit_limit_remark->sap_server = $sap_server;
            $credit_limit_remark->save();

            //            // review caf remarks
            //            if ($remarks_id == '2'){
            //                $this->pushReviewCaf($request);
            //
            //                $review_caf = new ReviewCaf();
            //                $review_caf->customer_code = $request->customer_code;
            //                $review_caf->cl_remarks_id = $credit_limit_remark->id;
            //                $review_caf->sap_server = $sap_server;
            //                $review_caf->status = '0';
            //                $review_caf->save();
            //            }
        }

        /* call webservice for rfc ************************* */
        $sap_credential = User::setSapServer($sap_server);
        $sap_user = User::setSapUser($sap_server, '0');

        // connection
        $sapConnection = [
            'ashost' => $sap_credential->host,
            'sysnr' => $sap_credential->system_number,
            'client' => $sap_credential->client,
            'user' => $sap_user->username,
            'passwd' => $sap_user->password,
        ];

        try {
            // invoke webservice
            $response = ApiController::executeSapFunction($sapConnection, 'ZFM_APR_RJT_CL', $params = [
                'I_ACT' => 'Y',
                'I_REMARKS' => $remarks->description ?? null,
                'I_VBELN' => $so_number,
            ], [
                'O_OUTPUT' => 'output',
            ]);

            // get response
            $response_status = $response['output'];

            if ($response_status == 'S') {  // success
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $response_status = 'X';

            SapLog::create([
                'type' => 'E',
                'parameter' => json_encode($params) ?? null,
                'sap_server' => $sap_server,
                'message' => $e->getMessage(),
            ]);
        }
        /* ******************************************** */

        // store logs to monitor webservice response
        $cl_log = new ClLog;
        $cl_log->so_number = $so_number;
        $cl_log->user_id = Auth::user()->id;
        $cl_log->description = $response;
        $cl_log->save();

        $cl_message = ClMessage::where('return', $response_status);

        // response is existing in sap return
        if ($cl_message->exists()) {
            $cl_message = $cl_message->get([
                'return',
                'description',
            ])
                ->first();
        } else {
            $cl_message = [
                'description' => $response_status,
            ];
        }

        return $cl_message;
    }

    public function cl_mass_approve(Request $request)
    {

        $ecoms = $request->ecoms;
        $remarks = $request->remarks;
        $checkedRemarks = $request->checkedRemarks;
        $status = '1';

        $checkedRemarks[] = $remarks;
        if (count($checkedRemarks) == 1 && $remarks == null) {

        } else {
            $checkedRemarks = array_filter($checkedRemarks, function ($value) {
                return $value !== null;
            });
        }

        $cl_messages = [];
        foreach ($ecoms as $ecom) { // loops e-commerce SO

            $so_number = $ecom['so_number'];
            $sap_server = $ecom['sap_server'];

            DB::beginTransaction();

            // update cl status
            CreditLimit::where('so_number', $so_number)
                ->update([
                    'status' => $status,
                    'updated_by' => Auth::user()->id,
                ]);

            // add remarks
            foreach ($checkedRemarks as $checkedRemark) {

                /* remarks id and description ****** */
                $remarks_id = '0';
                if (Remark::where('id', $checkedRemark)->exists()) {
                    $remarks = Remark::where('id', $checkedRemark)->first();
                    $remarks_id = $remarks->id;
                    $remarks_desc = $remarks->description;
                } else {
                    $remarks_desc = $checkedRemark;
                }
                /* ********************************* */

                $credit_limit_remark = new CreditLimitRemark;
                $credit_limit_remark->so_number = $so_number;
                $credit_limit_remark->remarks_id = $remarks_id;
                $credit_limit_remark->description = $remarks_desc;
                $credit_limit_remark->updated_by = Auth::user()->id;
                $credit_limit_remark->status = $status;
                $credit_limit_remark->sap_server = $sap_server;
                $credit_limit_remark->save();
            }

            /* call webservice for rfc ************************* */
            $sap_credential = User::setSapServer($sap_server);
            $sap_user = User::setSapUser($sap_server, '0');

            // connection
            $sapConnection = [
                'ashost' => $sap_credential->host,
                'sysnr' => $sap_credential->system_number,
                'client' => $sap_credential->client,
                'user' => $sap_user->username,
                'passwd' => $sap_user->password,
            ];

            try {
                // invoke webservice
                $response = ApiController::executeSapFunction($sapConnection, 'ZFM_APR_RJT_CL', $params = [
                    'I_ACT' => 'Y',
                    'I_REMARKS' => $remarks_desc ?? null,
                    'I_VBELN' => $so_number,
                ], [
                    'O_OUTPUT' => 'output',
                ]);

                // get response
                $response_status = $response['output'];

                if ($response_status == 'S') {  // success
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $response_status = 'X';
                $response = 'Error During Mass Approval';

                SapLog::create([
                    'type' => 'E',
                    'parameter' => json_encode($params) ?? null,
                    'sap_server' => $sap_server,
                    'message' => $e->getMessage(),
                ]);
            }
            /* ******************************************** */

            // store logs to monitor webservice response
            $cl_log = new ClLog();
            $cl_log->so_number = $so_number;
            $cl_log->user_id = Auth::user()->id;
            $cl_log->description = $response.' - Mass Approval';
            $cl_log->save();

            $cl_message = ClMessage::where('return', $response_status);

            // response is existing in sap return
            if ($cl_message->exists()) {
                $cl_message = $cl_message->get([
                    'return',
                    'description',
                ])->first();

            } else {
                $cl_message = [
                    'description' => $response,
                ];
            }
            $cl_message['so_number'] = $so_number;

            $cl_messages[] = $cl_message;
        }

        // get if has success and errors response
        $so_errors = collect($cl_messages)->where('return', '!=', 'S')->pluck('so_number')->toArray();
        $so_success = collect($cl_messages)->where('return', 'S')->pluck('so_number')->toArray();

        $results = [];

        // errors
        if (count($so_errors)) {
            $so_errors = implode(',', $so_errors);

            $results[] = [
                'return' => 'E',
                'description' => 'Sales Order '.$so_errors.' Encountered an Error.',
            ];
        }

        // success
        if (count($so_success)) {
            $results[] = [
                'return' => 'S',
                'description' => 'Success',
            ];
        }

        return $results;
    }

    public function cl_reject(Request $request)
    {
        $so_id = $request->id;
        $otherRemarks = $request->remarks;
        $checkedRemarks = $request->checkedRemarks;
        $so_number = $request->so_number;
        $company = $request->company;
        $sap_server = $request->sap_server;
        $status = '2';

        if (empty($otherRemarks)) {
            $request->validate([
                'checkedRemarks' => 'required',
            ]);
        } else {
            $checkedRemarks[] = $otherRemarks;
        }

        DB::beginTransaction();
        $credit_limit = CreditLimit::where('so_number', $so_number)
            ->update([
                'status' => $status,
                'updated_by' => Auth::user()->id,
            ]);

        foreach ($checkedRemarks as $checkedRemark) {

            /* remarks id and description ****** */
            $remarks_id = '0';
            if (Remark::where('id', $checkedRemark)->exists()) {
                $remarks = Remark::where('id', $checkedRemark)->first();
                $remarks_id = $remarks->id;
                $remarks_desc = $remarks->description;
            } else {
                $remarks_desc = $checkedRemark;
            }
            /* ********************************* */

            $credit_limit_remark = new CreditLimitRemark;
            $credit_limit_remark->so_number = $so_number;
            $credit_limit_remark->remarks_id = $remarks_id;
            $credit_limit_remark->description = $remarks_desc;
            $credit_limit_remark->updated_by = Auth::user()->id;
            $credit_limit_remark->status = $status;
            $credit_limit_remark->sap_server = $sap_server;
            $credit_limit_remark->save();

            //            // review caf remarks
            //            if ($remarks_id == '2'){
            //                $this->pushReviewCaf($request);
            //
            //                $review_caf = new ReviewCaf();
            //                $review_caf->customer_code = $request->customer_code;
            //                $review_caf->cl_remarks_id = $credit_limit_remark->id;
            //                $review_caf->sap_server = $sap_server;
            //                $review_caf->status = '0';
            //                $review_caf->save();
            //            }
        }
        DB::commit();

        return $credit_limit;
    }

    public function cl_mass_reject(Request $request)
    {
        $ecoms = $request->ecoms;
        $otherRemarks = $request->remarks;
        $checkedRemarks = $request->checkedRemarks;
        $status = '2';

        if (empty($otherRemarks)) {
            $request->validate([
                'checkedRemarks' => 'required',
            ]);
        } else {
            $checkedRemarks[] = $otherRemarks;
        }

        DB::beginTransaction();
        foreach ($ecoms as $ecom) { // loops e-commerce SO

            $so_number = $ecom['so_number'];
            $sap_server = $ecom['sap_server'];

            $credit_limit = CreditLimit::where('so_number', $so_number)
                ->update([
                    'status' => $status,
                    'updated_by' => Auth::user()->id,
                ]);

            foreach ($checkedRemarks as $checkedRemark) {

                /* remarks id and description ****** */
                $remarks_id = '0';
                if (Remark::where('id', $checkedRemark)->exists()) {
                    $remarks = Remark::where('id', $checkedRemark)->first();
                    $remarks_id = $remarks->id;
                    $remarks_desc = $remarks->description;
                } else {
                    $remarks_desc = $checkedRemark;
                }
                /* ********************************* */

                $credit_limit_remark = new CreditLimitRemark;
                $credit_limit_remark->so_number = $so_number;
                $credit_limit_remark->remarks_id = $remarks_id;
                $credit_limit_remark->description = $remarks_desc;
                $credit_limit_remark->updated_by = Auth::user()->id;
                $credit_limit_remark->status = $status;
                $credit_limit_remark->sap_server = $sap_server;
                $credit_limit_remark->save();
            }
        }
        DB::commit();

        return $credit_limit;
    }

    public function cl_remarks(Request $request)
    {
        $remarks = $request->remarks;
        $so_number = $request->so_number;
        $status = $request->status;

        $request->validate([
            'remarks' => 'required',
            'file_upload' => 'mimes:jpeg,bmp,png,gif,tiff,svg,pdf|nullable',
        ], [
            'mimes' => 'Only jpeg, bmp, png, gif, tiff, svg, pdf are allowed.',
        ]);

        // store image in folder
        $path = null;
        if (! empty($request->file('file_upload'))) {
            $sub_dir = Carbon::now()->format('M-Y');
            $path = $request->file('file_upload')->store('attachments/'.$sub_dir, 'public');
        }

        DB::beginTransaction();
        $credit_limit_remark = new CreditLimitRemark;
        $credit_limit_remark->so_number = $so_number;
        $credit_limit_remark->description = $remarks;
        $credit_limit_remark->updated_by = Auth::user()->id;
        $credit_limit_remark->status = $status;
        $credit_limit_remark->attachment = $path;
        $credit_limit_remark->save();
        DB::commit();

        return $credit_limit_remark;
    }

    public function cl_remarks_data($so_number)
    {
        $credit_limit_remarks = DB::select('CALL p_credit_limit_remarks(\''.$so_number.'\')');

        return $credit_limit_remarks;
    }

    public function get_so_vesion($so_number, $date_time)
    {
        return CreditLimit::where('so_number', $so_number)
            ->where('created_at', '<=', $date_time)
            ->orderByDesc('id')
            ->limit('1')
            ->get([
                'so_number',
                'version',
            ]);
    }

    public function fetchApproveRemarks()
    {
        return Remark::with('remarksStatuses')
            ->whereHas('remarksStatuses', function ($q) {
                $q->where('status_id', '1');
            })
            ->orderBy('description')
            ->get([
                'id',
                'description',
            ]);
    }

    public function fetchRejectRemarks()
    {
        return Remark::with('remarksStatuses')
            ->whereHas('remarksStatuses', function ($q) {
                $q->where('status_id', '2');
            })
            ->orderBy('description')
            ->get([
                'id',
                'description',
            ]);
    }

    /**
     * push customer details in CAF Portal
     * if remarks is review CAF
     */
    public function pushReviewCaf($request)
    {

        /**
         * get customer details from sap
         */
        $curl = curl_init();

        $sap_credential = User::setSapServer($request->sap_server);
        $sap_user = User::setSapUser($request->sap_server, '0');

        // set web service
        $url = env('CL_API_URL').'/customer';
        $port = env('CL_API_PORT');

        // set host
        $hosts = $sap_credential->host;
        $system_id = $sap_credential->system_id;
        $system_number = $sap_credential->system_number;
        $sap_name = rawurlencode($sap_credential->name);
        $client = $sap_credential->client;

        // set sap user
        $username = rawurlencode($sap_user->username);
        $password = rawurlencode($sap_user->password);

        // invoke webservice
        curl_setopt_array($curl, [
            CURLOPT_PORT => $port,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => "host=$hosts&system_id=$system_id&system_number=$system_number&username=$username&password=$password&sap_name=$sap_name&client=$client&customer_code=$request->customer_code",
            CURLOPT_HTTPHEADER => [
                'cache-control: no-cache',
                'content-type: application/x-www-form-urlencoded',
            ],
        ]);

        // get response
        $response = json_decode(curl_exec($curl));
        $err = curl_error($curl);
        curl_close($curl);

        /**
         * push customer details from SAP in CAF portal
         */
        $guzzClient = new Client();
        $url = env('CAF_API_URL');

        $body['company_code'] = Company::where('description', $request->company)->first()->code;
        $body['company_name'] = $request->company;
        $body['so_number'] = $request->so_number;

        $body['customer_code'] = $response->customer_code;
        $body['customer_name'] = $response->customer_name;
        $body['street'] = $response->street;
        $body['city'] = $response->city;
        $body['registration_number'] = $response->vat_reg_tin;

        $guzzRequest = $guzzClient->post($url, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'],
            'form_params' => $body,
        ]);
        $data = $guzzRequest->getBody();

        return $data;
    }

    public function logApiError(Request $request)
    {
        Storage::prepend($request->filename, Carbon::now().': '.$request->message);
    }
}
