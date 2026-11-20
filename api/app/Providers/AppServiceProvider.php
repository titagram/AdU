<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Limite sugli accessi: per codice fiscale **e** per indirizzo, perché limitare solo
         * l'IP premia chi distribuisce i tentativi. In M2 questa difesa verrà commentata con
         * un `// TODO` e diventerà il laboratorio L22.
         */
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtoupper((string) $request->input('codice_fiscale')).'|'.$request->ip()
            );
        });
    }
}
