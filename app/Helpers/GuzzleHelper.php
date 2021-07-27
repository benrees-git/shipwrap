<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use \GuzzleHttp\Exception\GuzzleException;
use \GuzzleHttp\Exception\BadResponseException;

class GuzzleHelper {

    static function guzzleRequest($method,$uri,$parms) {

        $client = new Client(); 
        
        try {
            $response = $client->request($method, $uri, $parms);  
            $header_link = array();
            $result = true;
            $status = $response->getStatusCode();
            $data = json_decode((string)$response->getBody(),true);
        } catch (BadResponseException $e) {
            $result = false;
            $status = $e->getResponse()->getStatusCode();
            $data = $e->getResponse()->getBody()->getContents();
        } catch (GuzzleException $e) {
            $result = false;
            $data = $e->getMessage();
            $status = 0;
        }
        
        return array('result' => $result, 'status' => $status,'data' => $data, 'modified' => false);
        
    }

}
