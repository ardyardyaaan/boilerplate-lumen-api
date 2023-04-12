<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/key', function() {
    return \Illuminate\Support\Str::random(32);
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->group(['prefix' => 'v1', 'namespace' => 'V1'], function () use ($router) {
        //USER
        $router->group(['prefix' => 'user', 'namespace' => 'User'], function () use ($router) {
            $router->post('create', 'UserController@create');
            $router->post('verify', 'UserController@verification');
            $router->post('request/otp', 'UserController@requestOtp');
            //AUTH REQUIRED
            $router->group(['middleware' => 'auth'], function () use ($router) {
                $router->get('list', 'UserController@list');
                $router->get('detail', 'UserController@detail');
            });
        });
        //AUTH
        $router->group(['prefix' => 'auth', 'namespace' => 'Auth'], function () use ($router) {
            $router->post('login', 'AuthController@login');
            $router->post('refresh/token', 'AuthController@refreshToken');
            //AUTH REQUIRED
            $router->group(['middleware' => 'auth'], function () use ($router) {
                $router->post('login/token', 'AuthController@loginToken');
                $router->get('logout', 'AuthController@logout');
            });
        });
        
    });
});
