<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

use App\Helpers\GuzzleHelper;

class PeoplevoxShippoCarrierController extends PeoplevoxCarrierController
{

    public function Despatch($config = null) {


        $this->ValidatePayload();
        $config = $this->PrepareConfig($config);
        
        // Authentication

        $token = substr(request()->header('Authorization'),7); // trim 'Bearer ' from request header auth 
        
        $shipmentPayload = $this->PrepareShipmentPayload(request()->despatchPackage,$config);

        $this->CreateShipment(['token' => $token],$shipmentPayload);
        
    }

    /*
    |--------------------------------------------------------------------------
    | Data Manipulation
    |--------------------------------------------------------------------------|
    */

    public function PrepareShipmentPayload($despatchPackage,$config) {
        
        $products = [];
        foreach($despatchPackage['items'] as $item) {

            if(!$item['components']) {
                $products[] = [
                    "name" => $item['itemType']['name'],
                    "sku" =>  $item['itemType']['itemCode'],
                    "qty" => $item['quantity'],
                    "value" => $item['salePrice'],
                    "weight" => $item['itemType']['weight'],
                    "template" => null,
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
        
        $parcel = array(
            'length'=> $despatchPackage['depth'] > 0 ? $despatchPackage['depth'] : 1,
            'width'=> $despatchPackage['width'] > 0 ? $despatchPackage['width'] : 1,
            'height'=> $despatchPackage['height'] > 0 ? $despatchPackage['height'] : 1,
            'distance_unit'=> $despatchPackage['heightMeasure'],
            'weight'=> $despatchPackage['weight'] > 0 ? $despatchPackage['weight'] : 1,
            'mass_unit'=> $despatchPackage['weightMeasure'],
        );
        
        $shipmentPayload = [
            'address_from'=> [
                'name' => 'Shawn Ippotle',
                'street1' => '215 Clayton St.',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94117',
                'country' => 'US'
            ],
            'address_to'=> [
                'name' => $despatchPackage['despatch']['salesOrder']['contactName'],
                'street1' =>  $despatchPackage['despatch']['salesOrder']['shippingAddress']['line1'],
                'street2' =>  $despatchPackage['despatch']['salesOrder']['shippingAddress']['line2'],
                'city' => $despatchPackage['despatch']['salesOrder']['shippingAddress']['townCity'],
                'state' => $despatchPackage['despatch']['salesOrder']['shippingAddress']['region'],
                'zip' => $despatchPackage['despatch']['salesOrder']['shippingAddress']['postCode'],
                'country' => $despatchPackage['despatch']['salesOrder']['shippingAddress']['countryCode'],
                'phone' => $despatchPackage['despatch']['salesOrder']['customer']['phone']
            ],
            'parcels'=> [
                [
                    'length'=> $despatchPackage['depth'],
                    'width'=> $despatchPackage['width'],
                    'height'=> $despatchPackage['height'],
                    'distance_unit'=> $despatchPackage['heightMeasure'],
                    'weight'=> $despatchPackage['weight'],
                    'mass_unit'=> $despatchPackage['weightMeasure']
                ]
            ],
            'meta_data' => $despatchPackage['despatch']['salesOrder']['salesOrderNumber'] . ($despatchPackage['packageNumber']  > 1 ? "_" . $despatchPackage['packageNumber'] : null),
            'extra' => [
                "reference_1" => $despatchPackage['despatchPackageId'],
                "reference_2" => $despatchPackage['despatch']['salesOrder']['salesOrderNumber'],
            ],
            'async'=> false
        ];

        return $shipmentPayload;

    }

    function CreateShipment($Connection,$shipmentPayload) {

        $params = $shipmentPayload;
        $retry = 0;

        while($retry < 3) {

            $headers = ['Accept' => 'application/json', 'Authorization' => "ShippoToken ".$Connection['token']];
            $response = GuzzleHelper::guzzleRequest('POST', 'https://api.goshippo.com/shipments/', ['json' => $params, 'headers' => $headers]);
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