<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * DIFETTO INTENZIONALE (L19, capitolo 1): la console di diagnostica.
 *
 * Ogni query eseguita dall'API viene annotata in un file, e la pagina `/debug` la mostra a
 * chiunque senza autenticazione. Il file è pensato per durare il tempo di una verifica: qui
 * è rimasto acceso in produzione. Fra le righe compaiono i codici fiscali di chi ha appena
 * fatto accesso, cioè dati personali di persone che non hanno acconsentito.
 *
 * La correzione è toglierlo, non proteggerlo: una console di debug non si mette in produzione.
 */
class RegistraQuery
{
    private const MASSIMO_RIGHE = 200;

    private static array $raccolte = [];

    public function handle(Request $request, Closure $next): Response
    {
        self::$raccolte = [];

        DB::listen(function ($query) {
            self::$raccolte[] = sprintf(
                '[%s] %s | %s | %.1f ms',
                now()->format('Y-m-d H:i:s'),
                $query->sql,
                json_encode($query->bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $query->time,
            );
        });

        $risposta = $next($request);

        $this->salva();

        return $risposta;
    }

    private function salva(): void
    {
        if (self::$raccolte === []) {
            return;
        }

        $file = storage_path('app/debug/query.log');
        @mkdir(dirname($file), 0775, true);

        $precedenti = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
        $tutte = array_slice(array_merge($precedenti, self::$raccolte), -self::MASSIMO_RIGHE);

        @file_put_contents($file, implode("\n", $tutte)."\n");
    }
}
