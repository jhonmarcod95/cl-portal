<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditLimit;
use App\Models\RejectRemarks;
use App\Models\OpenRemark;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditLimitController extends Controller
{
    public function index(){
        session(['header_text' => 'Credit Limit Records']);

        $rejectRemarks = RejectRemarks::get()
            ->pluck(
                'description',
                'id'
            );

        $openRemarks = OpenRemark::get()
            ->pluck(
                'description',
                'id'
            );

        return view('cl.index', compact(
            'rejectRemarks',
            'openRemarks'
        ));
    }

    public function indexData($dateFrom, $dateTo){
        $user_companies = implode(',', Auth::user()->company()->pluck('description')->toArray()); // use to filter company based on users

        return DB::select("CALL p_credit_limit('$dateFrom', '$dateTo', '%%', '$user_companies')");
//        return CreditLimit::join('statuses', 'credit_limits.status', 'statuses.id')
//            ->whereBetween('date_issue', [$dateFrom, $dateTo])
//            ->get([
//                'credit_limits.*',
//                'statuses.description',
//            ]);
    }

    public function testApi(){
        // connection
        $sapConnection = [
            'ashost' => '172.17.2.36',
            'sysnr' => '02',
            'client' => '888',
            'user' => 'vp_user',
            'passwd' => 'vpmarco1@',
        ];

        $response = ApiController::executeSapFunction($sapConnection, 'ZFM_EXPORT_FROM_SPOOL', [
            'SPOOLNO:int' => '123',
            'SONO' => '123',
        ],  null);

        dd($response);
    }

    public function storeClToStorage(){

        // result
        $stored_cls = [];

        // sap drive names
        $lfug_path = 'A:\\';
        $pfmc_path = 'A:\\';

        $files_lfug = \File::files($lfug_path);
        $files_pfmc = \File::files($pfmc_path);

        $files = array_merge($files_lfug, $files_pfmc);

        foreach($files as $file) {
            $file = pathinfo($file);

            $dirname = $file['dirname'];
            $basename = $file['basename'];
            $date_modified = date('Y-m-d H:i:s', filemtime($dirname . $basename));

            $file_explode = explode('_', $file['filename'] . '_' . $date_modified);


            // count only those corrected formats (ex: 7275_2110100457_LFUG_20190209_016)
            if (count($file_explode) == 6){
                $filenames[] = $file_explode;
            }
        }

        // generate filename
        foreach ($filenames as $filename){
            $cl_names[] = [
                'spool' => trim($filename[0]),
                'so_number' => $filename[1],
                'company' => $filename[2],
                'date_issue' => date("Y-m-d", strtotime($filename[3])),
                'version' => $filename[4],
                'pushed_at' => $filename[5],
            ];
        }

        // 1 week date filter for rfc call
        $cl_names = collect($cl_names)
            ->where('date_issue', '>=', Carbon::now()->addDays(-6)->format('Y-m-d'))
            ->where('date_issue', '<=', Carbon::now()->format('Y-m-d'));


        // store CL
        foreach ($cl_names as $cl_name){

            $spool = $cl_name['spool'];
            $so_number = $cl_name['so_number'];
            $company = $cl_name['company'];
            $date_issue = $cl_name['date_issue'];
            $version = $cl_name['version'];
            $pushed_at = $cl_name['pushed_at'];

            // check if CL pdf already exists
            if(!CreditLimit::where('spool', $spool)
                ->where('so_number', $so_number)
                ->where('company', $company)
                ->where('date_issue', $date_issue)
                ->where('version', $version)
                ->exists()){

                DB::beginTransaction();

                // rfc call for spool
                $curl = curl_init();

                // set server
                $sap_server = Company::where('description', $company)->pluck('sap_server')->first();
                $sap_credential = User::setSapServer($sap_server);
                $sap_user = User::setSapUser($sap_server,'0');

                // set web service
                $url = User::$url . '/export_from_spool';
                $port = User::$port;

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
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "host=$hosts&system_id=$system_id&system_number=$system_number&username=$username&password=$password&sap_name=$sap_name&client=$client&so_number=$so_number&spool_number=$spool",
                    CURLOPT_HTTPHEADER => [
                        "cache-control: no-cache",
                        "content-type: application/x-www-form-urlencoded",
                        "postman-token: b8a4bff6-8559-a7fb-01d7-6f097b0d93c2"
                    ],
                ]);

                // get response
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);

                $response = json_decode($response);
                $request_date = $response->request_date;
                $customer_name = $response->customer_name;

                if ($request_date == '0000-00-00' && $customer_name == ''){
                    // not store if no request date and customer
                }
                else{
                    $credit_limit = new CreditLimit();
                    $credit_limit->spool = $spool;
                    $credit_limit->so_number = $so_number;
                    $credit_limit->company = $company;
                    $credit_limit->request_date = $request_date;
                    $credit_limit->customer_name = $customer_name;
                    $credit_limit->date_issue = $date_issue;
                    $credit_limit->version = $version;
                    $credit_limit->status = '0';
                    $credit_limit->sap_server = $sap_server;
                    $credit_limit->pushed_at = $pushed_at;
                    $credit_limit->save();

                    /* copy the pdf file to storage folder  *****/
                    $pdf_name = str_pad($spool, 9, ' ', STR_PAD_LEFT) . '_' . $so_number . '_' . $company . '_' . str_replace('-', '', $date_issue)  . '_' . sprintf("%03d", $version) . '.pdf';

                    // set drive destination based on sap server
                    $network_drive = '';
                    if($sap_server == 'LFUG'){
                        $network_drive = $lfug_path;
                    }
                    else{
                        $network_drive = $pfmc_path;
                    }

                    \File::copy($network_drive . $pdf_name, base_path('public/storage/cl_approve/' . $sap_server . '/' . $so_number . '_' . $version . '.pdf'));
                    /* ******************************************/

                    $stored_cls[] = $so_number;
                }

                DB::commit();
            }
        }
    }
    
}
