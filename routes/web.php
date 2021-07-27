<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------|
*/

$router->group(['prefix' => 'api'], function () use ($router) {

    /*
    |--------------------------------------------------------------------------
    | Peoplevox Routes
    |--------------------------------------------------------------------------|
    */
    
    $router->group(['prefix' => 'peoplevox'], function () use ($router) {
       
        $router->group(['prefix' => 'test'], function () use ($router) {
            $router->post('despatch', 'PeoplevoxCarrierController@Despatch');
            $router->post('despatch/config/{config}', 'PeoplevoxCarrierController@Despatch');
        });

        $router->group(['prefix' => 'shiptheory'], function () use ($router) {
            $router->post('despatch', 'PeoplevoxShiptheoryCarrierController@Despatch');
            $router->post('despatch/config/{config}', 'PeoplevoxShiptheoryCarrierController@Despatch');
        });

        $router->group(['prefix' => 'shippo'], function () use ($router) {
            $router->post('despatch', 'PeoplevoxShippoCarrierController@Despatch');
            $router->post('despatch/config/{config}', 'PeoplevoxShippoCarrierController@Despatch');
        });

    });

});
