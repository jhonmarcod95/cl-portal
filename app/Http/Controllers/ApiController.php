<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use GuzzleHttp\Client;

class ApiController extends Controller
{
    public function getCustomer($company_code, $customer_code)
    {

        $sap_server = Company::where('code', $company_code)->pluck('sap_server')->first();
        $customer_code = str_pad($customer_code, 10, '0', STR_PAD_LEFT);

        /**
         * get customer details from sap
         */
        $curl = curl_init();

        $sap_credential = User::setSapServer($sap_server);
        $sap_user = User::setSapUser($sap_server, '0');

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
            CURLOPT_POSTFIELDS => "host=$hosts&system_id=$system_id&system_number=$system_number&username=$username&password=$password&sap_name=$sap_name&client=$client&customer_code=$customer_code&company_code=$company_code",
            CURLOPT_HTTPHEADER => [
                'cache-control: no-cache',
                'content-type: application/x-www-form-urlencoded',
            ],
        ]);

        // get response
        $response = json_decode(curl_exec($curl), true);
        $err = curl_error($curl);
        curl_close($curl);

        return $response;
    }

    private function getPercentage($value, $total)
    {
        if ($total > 0) {
            $result = ($value / $total) * 100;
        } else {
            $result = 0;
        }

        return $result;
    }

    public static function readSapTableApi($connection, $table)
    {
        $client = new Client;
        $response = $client->request('GET', env('SAP_API').'/read-table', ['query' => ['connection' => $connection, 'table' => $table]]);

        return collect(json_decode($response->getBody()));
    }

    public static function executeSapFunction($connection, $function, $parameters, $return)
    {
        $client = new Client;
        $response = $client->request('POST', env('SAP_API').'/execute-fm', [
            'form_params' => [
                'connection' => $connection,
                'function' => $function,
                'parameters' => $parameters,
                'return' => $return,
            ],
        ]);

        return collect(json_decode($response->getBody()));
    }
}
