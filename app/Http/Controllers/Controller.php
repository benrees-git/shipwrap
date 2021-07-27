<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    public function ValidatePayload() {

        $this->validate(request(), [
            'request.metadata.schemaVersion' => 'in:2.0',
            'despatchPackage' => 'required',
            'printRequests.*.documentReference' => 'in:shipping_label,customs_document,returns_label'
        ]);

    }

    public function PrepareConfig($config) {

        $config = json_decode(urldecode($config),true);

        if(!array_key_exists('so_attribute_for_terms_of_trade',$config) OR 
        !in_array($config['so_attribute_for_terms_of_trade'],[1,2,3,4,5])) {
            $config['so_attribute_for_terms_of_trade'] = null;
        }

        if(!array_key_exists('so_attribute_for_ioss',$config) OR 
        !in_array($config['so_attribute_for_ioss'],[1,2,3,4,5])) {
            $config['so_attribute_for_ioss'] = null;
        }

        return $config;

    }

    protected function RespondWithResult($status,$message,$data = []) {
        
        return response()->json([
                'status' => $status,
                'message' => $message,
                'data' => $data                
            ],200);

    }
    
}
