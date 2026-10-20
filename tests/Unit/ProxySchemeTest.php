<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('generates HTTPS asset and route URLs behind the deployment proxy', function () {
    Route::get('/_proxy-scheme', fn () => [
        'asset' => asset('build/assets/app.css'),
        'route' => route('login'),
    ]);

    $this->withServerVariables([
        'HTTP_HOST' => 'ajm-bengkel-laravel-production.up.railway.app',
        'SERVER_PORT' => 80,
        'HTTPS' => 'off',
        'REMOTE_ADDR' => '10.0.0.2',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('http://ajm-bengkel-laravel-production.up.railway.app/_proxy-scheme')
        ->assertOk()
        ->assertJson([
            'asset' => 'https://ajm-bengkel-laravel-production.up.railway.app/build/assets/app.css',
            'route' => 'https://ajm-bengkel-laravel-production.up.railway.app/login',
        ]);
});

it('keeps direct local HTTP development working without forwarded headers', function () {
    Route::get('/_proxy-scheme', fn () => ['asset' => asset('build/assets/app.css')]);

    $this->withServerVariables([
        'HTTP_HOST' => 'localhost',
        'SERVER_PORT' => 80,
        'HTTPS' => 'off',
    ])->get('http://localhost/_proxy-scheme')
        ->assertOk()
        ->assertJson(['asset' => 'http://localhost/build/assets/app.css']);
});
