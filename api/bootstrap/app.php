<?php

use App\Http\Middleware\AutenticaJwt;
use App\Http\Middleware\RegistraAccesso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.jwt' => AutenticaJwt::class]);

        // Registro degli accessi su tutta la superficie dell'API: è il materiale del
        // capitolo sulla difesa e il bersaglio del laboratorio L24.
        $middleware->appendToGroup('api', RegistraAccesso::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
