<?php

use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::get('/up', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The application is only exposed through Cloudflare / Traefik / Nginx.
        // Trust the direct proxy so Laravel honors X-Forwarded-Proto=https when
        // generating Filament and Livewire asset URLs.
        $middleware->trustProxies(at: '*');

        $middleware->preventRequestsDuringMaintenance(except: ['/up']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
