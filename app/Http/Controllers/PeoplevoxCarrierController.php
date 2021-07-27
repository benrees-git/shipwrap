<?php

namespace App\Http\Controllers;

class PeoplevoxCarrierController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------|
    */

    public function ValidatePayload() {

        $this->validate(request(), [
            'request.metadata.schemaVersion' => 'in:2.0',
            'despatchPackage' => 'required',
            'printRequests.*.documentReference' => 'in:shipping_label,customs_document,returns_label'
        ]);

    }

    public function PrepareConfig($config) {

        $config = json_decode(urldecode($config),true);

        $salesOrder = [
            "attribute1",
            "attribute2",
            "attribute3",
            "attribute4",
            "attribute5",
        ];

        $itemType = [
            "attribute1",
            "attribute2",
            "attribute3",
            "attribute4",
            "attribute5",
            "attribute6",
            "attribute7",
            "attribute8",
            "attribute9",
            "attribute10",
            "attribute11",
            "attribute12",
            "attribute13",
            "attribute14",
            "attribute15",
            "salesOrderItemAttribute1",
            "salesOrderItemAttribute2",
            "salesOrderItemAttribute3",
        ];

        // * Terms of trade code *// 
        if(!array_key_exists('so_field_for_terms_of_trade',$config) OR 
        !in_array($config['so_field_for_terms_of_trade'],$salesOrder)) {
            $config['so_field_for_terms_of_trade'] = null;
        }

        // * IOSS Code *// 
        if(!array_key_exists('so_field_for_ioss',$config) OR 
        !in_array($config['so_field_for_ioss'],$salesOrder)) {
            $config['so_field_for_ioss'] = null;
        }

        // * HS code *// 
        if(!array_key_exists('it_field_for_hs_code',$config) OR 
        !in_array($config['it_field_for_hs_code'],$itemType)) {
            $config['it_field_for_hs_code'] = null;
        }

        return $config;

    }

    /*
    |--------------------------------------------------------------------------
    | Responses
    |--------------------------------------------------------------------------|
    */

    protected function RespondWithResult($status,$message,$data = []) {
        
        return response()->json([
                'status' => $status,
                'message' => $message,
                'data' => $data                
            ],200);

    }

    /*
    |--------------------------------------------------------------------------
    | Base method
    |--------------------------------------------------------------------------|
    */
    
    public function Despatch($config = null) {
        
        $this->ValidatePayload();
        $config = $this->PrepareConfig($config);

        $token = substr(request()->header('Authorization'),7); // trim 'Bearer ' from request header auth 


        $trackingNumbers = [
            [ 
                'trackingType' => 'outbound', 
                'trackingNumber' => "T".Carbon::now()->timestamp 
            ]
        ];

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
