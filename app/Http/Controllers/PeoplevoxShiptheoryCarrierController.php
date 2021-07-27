<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

use App\Helpers\GuzzleHelper;

class PeoplevoxShiptheoryCarrierController extends PeoplevoxCarrierController
{

    public function Despatch($config = null) {


        $this->ValidatePayload();
        $config = $this->PrepareConfig($config);
        
        // Authentication

        $token = substr(request()->header('Authorization'),7); // trim 'Bearer ' from request header auth 
        $credentials = explode(";",$token);
        if(count($credentials) != 2) {
            return $this->RespondWithResult('fail','Cannot parse credentials');
        }

        $ShiptheoryResponse = $this->Authenticate(['email' => $credentials[0],'password' => $credentials[1]]);
        if(!$ShiptheoryResponse['result']) {
            return $this->RespondWithResult('fail',$ShiptheoryResponse['data']);
        }
        $external_token =  $ShiptheoryResponse['data']['data']['token'];

        // Create / Process Shipment

        $shipmentPayload = $this->PrepareShipmentPayload(request()->despatchPackage,$config);
        
        $ShiptheoryResponse = $this->BookShipment(['token' => $external_token],$shipmentPayload);
        if(!$ShiptheoryResponse['result']) {
            return $this->RespondWithResult('fail',$ShiptheoryResponse['data']);
        }
        if(!$ShiptheoryResponse['data']['success']) {
            return $this->RespondWithResult('fail',$ShiptheoryResponse['data']['message']);
        } else {
            $ShiptheoryResponse = $this->ViewShipment(['token' => $external_token],$shipmentPayload['reference']);
            if(!$ShiptheoryResponse['result']) {
                return $this->RespondWithResult('fail',$ShiptheoryResponse['data']);
            } else {

                $trackingNumbers = [
                    [ 
                        'trackingType' => 'outbound', 
                        'trackingNumber' => $ShiptheoryResponse['data']['shipment']['tracking_number']
                    ]
                ];

                // Label returned as $ShiptheoryResponse['data']['shipment']['label'] as a base 64 encoded PDF. C
                // TO DO - store label as a file somehere and provide single use URL. 
        
                $newPrintRequests = [];
                foreach(request()->printRequests as $oldPrintRequest) {
                    $newPrintRequests[] = [
                        'printRequestId' => $oldPrintRequest['printRequestId'],
                        'status' => 'not required'
                    ];
                }
        
                return $this->RespondWithResult('success',null,[
                    'trackingNumbers' => $trackingNumbers,
                    'newPrintRequests' => $newPrintRequests
                ]);

            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Data Manipulation
    |--------------------------------------------------------------------------|
    */

    public function PrepareShipmentPayload($despatchPackage,$config) {

        $contactNameArray = explode(" ",$despatchPackage['despatch']['salesOrder']['contactName']);
        $products = [];
        foreach($despatchPackage['items'] as $item) {

            if(!$item['components']) {
                $products[] = [
                    "name" => $item['itemType']['name'],
                    "sku" =>  $item['itemType']['itemCode'],
                    "qty" => $item['quantity'],
                    "value" => $item['salePrice'],
                    "weight" => $item['itemType']['weight']
                ];
            } else {

                $component_quantity = 0;
                foreach($item['components'] as $component) {
                    $component_quantity+= $component['quantity'];
                }

                $componentSalePrice = number_format((float)$item['salePrice'] / $component_quantity,2);

                foreach($item['components'] as $component) {
                    $products[] = [
                        "name" => $component['itemType']['name'],
                        "sku" =>  $component['itemType']['itemCode'],
                        "qty" => $component['quantity'],
                        "value" => $componentSalePrice ,
                        "weight" => $component['itemType']['weight']
                    ];
                }
            }

        }

        $shipmentPayload = [
            "reference" => $despatchPackage['despatchPackageId'],
            "reference2" => $despatchPackage['despatch']['salesOrder']['salesOrderNumber'] . ($despatchPackage['packageNumber']  > 1 ? "_" . $despatchPackage['packageNumber'] : null),
            "shipment_detail" => [
                "weight" => $despatchPackage['weight'],
                "parcels" => 1,
                "value" => 135.18,
                "shipping_price" => 3.99,
                "reference3" => $despatchPackage['despatch']['salesOrder']['salesOrderNumber'],
                "sales_source" => $despatchPackage['despatch']['salesOrder']['channelName'],
                //"ship_date" => Carbon::now()->format('Y-m-d'),
                "rules_metadata" => $despatchPackage['serviceType']['code'],
                "duty_tax_number" => "IM123456789",
                "duty_tax_number_type" => "IOSS"
            ],
            "recipient" => [
                //"company" => "Beard Supplies Co",
                "firstname" => array_shift($contactNameArray),
                "lastname" => implode(" ",$contactNameArray),
                "address_line_1" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['line1'],
                "address_line_2" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['line2'],
                "city" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['townCity'],
                "county" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['region'],
                "postcode" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['postCode'],
                "telephone" => $despatchPackage['despatch']['salesOrder']['customer']['phone'],
                "email" => $despatchPackage['despatch']['salesOrder']['customer']['email'],
                "country" => $despatchPackage['despatch']['salesOrder']['shippingAddress']['countryCode'],
                //"tax_number" => "GB123456"
            ],
            // "sender" => [
            //     "company" => "Hair Wholesaler Co.",
            //     "firstname" => "Julian",
            //     "lastname" => "Bashir",
            //     "address_line_1" => "65 Horfield Retail Park",
            //     "city" => "Bath",
            //     "postcode" => "ba1 2jw",
            //     "telephone": "0117123111",
            //     "country" => "GB"
            // ],
            "products" => $products,
            // "packages" => [
            //     ["id" => 100001, "weight" => 10.50],
            // ]
        ];

        return $shipmentPayload;

    }

    /*
    |--------------------------------------------------------------------------
    | External API Requests
    |--------------------------------------------------------------------------|
    */

    function Authenticate($Connection) {
                
        $params = [
            'email' => $Connection['email'],
            'password' => $Connection['password']
        ];
        $retry = 0;

        while($retry < 3) {

            $headers = ['Accept' => 'application/json'];
            $response = GuzzleHelper::guzzleRequest('POST', 'https://api.shiptheory.com/v1/token', ['json' => $params, 'headers' => $headers]);
            if($response['result']) {
                return $response;
            } else {
                $retry++;
                if($response['status'] === 401) {
                    //$Connection->access_token = (new ConnectionController)->refreshConnectionToken($Connection->id);
                    //$response['modified'] = true;
                    return $response;
                } 
            }
        }
        
        return $response;
        
    }

    function BookShipment($Connection,$shipmentPayload) {

        $params = $shipmentPayload;
        $retry = 0;

        while($retry < 3) {

            $headers = ['Accept' => 'application/json', 'Authorization' => "Bearer ".$Connection['token']];
            $response = GuzzleHelper::guzzleRequest('POST', 'https://api.shiptheory.com/v1/shipments', ['json' => $params, 'headers' => $headers]);
            if($response['result']) {
                return $response;
            } else {
                $retry++;
                if($response['status'] === 401) {
                    //$Connection->access_token = (new ConnectionController)->refreshConnectionToken($Connection->id);
                    //$response['modified'] = true;
                    return $response;
                } 
            }
        }
        
        return $response;

    }

    function ViewShipment($Connection,$shipmentReference) {

        $params = null;
        $retry = 0;

        while($retry < 3) {

            $headers = ['Accept' => 'application/json', 'Authorization' => "Bearer ".$Connection['token']];
            $response = GuzzleHelper::guzzleRequest('GET', 'https://api.shiptheory.com/v1/shipments/'.$shipmentReference, ['json' => $params, 'headers' => $headers]);
            if($response['result']) {
                return $response;
            } else {
                $retry++;
                if($response['status'] === 401) {
                    //$Connection->access_token = (new ConnectionController)->refreshConnectionToken($Connection->id);
                    //$response['modified'] = true;
                    return $response;
                } 
            }
        }
        
        return $response;

    }
}
