<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:store-credit-limit')]
#[Description('Command description')]
class StoreCreditLimit extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $started_at = Carbon::now();
        $start_time = microtime(true);


        try{
            // result
            $stored_cls = [];

            // sap drive names
            $lfug_path = env('LFUG_DRIVE');
            $pfmc_path = env('PFMC_DRIVE');
            $hana_path = env('HANA_DRIVE');
            $amd_path = env('AMD_DRIVE');

            $files_lfug = \File::files($lfug_path);
            $files_pfmc = \File::files($pfmc_path);
            $files_hana = \File::files($hana_path);
            $files_amd = \File::files($amd_path);

            $files = array_merge($files_lfug, $files_pfmc, $files_hana, $files_amd);

            $filenames = [];
            foreach($files as $file) {
                $file = pathinfo($file);

                if ($file['extension'] == 'PDF'){
                    $dirname = $file['dirname'];
                    $basename = $file['basename'];

                    $date_modified = date('Y-m-d H:i:s', filemtime($dirname . '/' . $basename));

                    $file_explode = explode('_', $file['filename'] . '_' . $date_modified);


                    // count only those corrected formats (ex: 7275_2110100457_LFUG_20190209_016 or 7275_2110100457_LFUG_20190209_016_ECOM)
                    if (count($file_explode) >= 6){
                        $filenames[] = $file_explode;
                    }
                }
            }

            // generate filename
            $cl_names = [];
            foreach ($filenames as $filename){

                $arr_keys = count(array_keys($filename));

                if ($arr_keys == 6){ // default
                    $cl_names[] = [
                        'spool' => trim($filename[0]),
                        'so_number' => $filename[1],
                        'company' => $filename[2],
                        'date_issue' => date("Y-m-d", strtotime($filename[3])),
                        'version' => $filename[4],
                        'pushed_at' => $filename[5],
                    ];
                }
                else{ // ecom
                    $cl_names[] = [
                        'spool' => trim($filename[0]),
                        'so_number' => $filename[1],
                        'company' => $filename[2],
                        'date_issue' => date("Y-m-d", strtotime($filename[3])),
                        'version' => $filename[4],
                        'type' => $filename[5],
                        'pushed_at' => $filename[6],
                    ];
                }
            }

            // 1 week date filter for rfc call
            $days_prior_download = -6;
            $cl_names = collect($cl_names)
                ->where('date_issue', '>=', Carbon::now()->addDays($days_prior_download)->format('Y-m-d'))
                ->where('date_issue', '<=', Carbon::now()->format('Y-m-d'));

            foreach ($cl_names as $cl_name){

                $spool = $cl_name['spool'];
                $so_number = $cl_name['so_number'];
                $company = $cl_name['company'];
                $date_issue = $cl_name['date_issue'];
                $version = $cl_name['version'];
                $pushed_at = $cl_name['pushed_at'];
                $type = (isset($cl_name['type'])) ? $cl_name['type'] : null;
                $order_type = null;

                // check if CL pdf already exists
                if(!CreditLimit::where('spool', $spool)
                    ->where('so_number', $so_number)
                    ->where('company', $company)
                    ->where('date_issue', $date_issue)
                    ->where('version', $version)
                    ->exists()){

                    DB::beginTransaction();

                    // set server
                    $sap_server = Company::where('description', $company)->pluck('sap_server')->first();
                    $sap_credential = User::setSapServer($sap_server);
                    $sap_user = User::setSapUser($sap_server,'0');


                    // connection
                    $sapConnection = [
                        'ashost' => $sap_credential->host,
                        'sysnr' => $sap_credential->system_number,
                        'client' => $sap_credential->client,
                        'user' => $sap_user->username,
                        'passwd' => $sap_user->password,
                    ];

                    // invoke webservice
                    $results = [
                        'REQ_DATE' => 'request_date:date',
                        'CUS_NAME' => 'customer_name',
                        'CUS_NO' => 'customer_code',
                        'CUS_CLIMIT' => 'customer_cl_limit',
                        'PAY_TERMS' => 'payment_terms',
                        'PLANT_CODE' => 'plant_code',
                        'USERNAME' => 'sap_user',
                    ];

                    if ($sap_server != 'HANA'){
                        $results['ORDER_TYPE'] = 'order_type';
                    }

                    if ($sap_server == 'HANA'){
                        $results['CREDIT_STATUS'] = 'credit_status';
                    }

                    $response = ApiController::executeSapFunction($sapConnection, 'ZFM_EXPORT_FROM_SPOOL', [
                        'SPOOLNO:int' => $spool,
                        'SONO' => $so_number,
                    ],  $results);

                    $request_date = $response['request_date'];
                    $customer_name = $response['customer_name'];
                    $customer_code = $response['customer_code'];
                    $customer_cl_limit = $response['customer_cl_limit'];
                    $payment_terms = $response['payment_terms'];
                    $plant_code = $response['plant_code'];
                    $sap_user = $response['sap_user'];
                    $credit_status = $response['credit_status'] ?? '';

                    if ($sap_user){
                        
                        if ($sap_server != 'HANA') {
                            $order_type = $response['order_type'];
                        }

                        if ($request_date == '0000-00-00' && $customer_name == ''){
                            // not store if no request date and customer
                        }
                        else{
                            $credit_limit = new CreditLimit();
                            $credit_limit->spool = $spool;
                            $credit_limit->so_number = $so_number;
                            $credit_limit->company = $company;
                            $credit_limit->request_date = $request_date;
                            $credit_limit->customer_code = $customer_code;
                            $credit_limit->customer_name = $customer_name;
                            $credit_limit->customer_cl_limit = $customer_cl_limit;
                            $credit_limit->date_issue = $date_issue;
                            $credit_limit->version = $version;
                            $credit_limit->status = $credit_status == 'X' ? null : '0';
                            $credit_limit->sap_server = $sap_server;
                            $credit_limit->pushed_at = $pushed_at;
                            $credit_limit->type = $type;
                            $credit_limit->payment_terms = $payment_terms;
                            $credit_limit->order_type = $order_type;
                            $credit_limit->plant_code = $plant_code;
                            $credit_limit->sap_user = $sap_user;
                            $credit_limit->save();

                            /* copy the pdf file to storage folder  *****/
                            $type_file = '';
                            if ($type == 'ECOM'){ // ecom scenario
                                $type_file = '_' . $type;
                            }

                            if (in_array($sap_server, ['HANA', 'AMD'])){
                                $pdf_name = str_pad($spool, 0, '', STR_PAD_LEFT) . '_' . $so_number . '_' . $company . '_' . str_replace('-', '', $date_issue)  . '_' . sprintf("%03d", $version) . $type_file . '.PDF';
                            }
                            else{
                                $pdf_name = str_pad($spool, 9, ' ', STR_PAD_LEFT) . '_' . $so_number . '_' . $company . '_' . str_replace('-', '', $date_issue)  . '_' . sprintf("%03d", $version) . $type_file . '.PDF';
                            }


                            // set drive destination based on sap server
                            $network_drive = '';
                            if($sap_server == 'LFUG'){
                                $network_drive = $lfug_path;

                            }
                            else if($sap_server == 'PFMC'){
                                $network_drive = $pfmc_path;
                            }
                            else if($sap_server == 'AMD'){
                                $network_drive = $amd_path;
                            }
                            else{
                                $network_drive = $hana_path;
                            }

                            $network_drive = $network_drive . '/' . $pdf_name;

                            \File::copy($network_drive, base_path('public/storage/cl_approve/' . $sap_server . '/' . $so_number . '_' . $version . '.pdf'));
                            /* ******************************************/

                            $stored_cls[] = $so_number;
                        }

                        $stored_cls[] = $so_number;
                    }

                    /*end test*/

                    DB::commit();
                }
            }

            $status = 'success';
        }
        catch (\Exception $e){
            $status = 'error : ' . $e;
        }


        $time_elapsed_secs = microtime(true) - $start_time;
        $duration_in_seconds = $time_elapsed_secs;

        $message = 'Job Started @ ' . $started_at . ', Finish @ ' . $duration_in_seconds . ' seconds, ' . $status;
        $this->generateLog($message);

        return $status;
        
    }

    private function generateLog($message){
        $filename = 'store-credit-limit-' . Carbon::now()->format('Y-m') . '.log';
        Storage::prepend($filename, '------------------------------------------------------------------------'); // error message
        Storage::prepend($filename, $message); // error message
    }
}
