<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api-modules.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'cors'           => \App\Http\Middleware\Cors::class,
            'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
            'validate.json'  => \App\Http\Middleware\ValidateJsonRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
