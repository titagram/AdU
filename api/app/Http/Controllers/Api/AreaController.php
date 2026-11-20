<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cartella;
use App\Models\Pratica;
use App\Models\Rimborso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Area riservata del cittadino.
 *
 * M1: ogni lettura è filtrata per il codice fiscale di chi ha fatto la richiesta e ogni
 * singola risorsa viene verificata come sua. In M3 i filtri diventeranno `find($id)` e
 * il corso avrà il suo IDOR (L01/L02).
 */
class AreaController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $utente = $request->attributes->get('utente');

        return response()->json([
            'utente' => app(AuthController::class)->profilo($utente),
            'conteggi' => [
                'cartelle' => Cartella::where('codice_fiscale', $utente->codice_fiscale)->count(),
                'pratiche' => Pratica::where('codice_fiscale', $utente->codice_fiscale)->count(),
                'rimborsi' => Rimborso::where('codice_fiscale', $utente->codice_fiscale)->count(),
            ],
        ]);
    }

    public function cartelle(Request $request): JsonResponse
    {
        $cf = $request->attributes->get('utente')->codice_fiscale;

        return response()->json([
            'elementi' => Cartella::where('codice_fiscale', $cf)->orderByDesc('anno')->get(),
        ]);
    }

    public function cartella(Request $request, int $id): JsonResponse
    {
        $cf = $request->attributes->get('utente')->codice_fiscale;
        $cartella = Cartella::where('codice_fiscale', $cf)->find($id);

        if ($cartella === null) {
            return response()->json(['errore' => 'Cartella non trovata.'], 404);
        }

        return response()->json(['cartella' => $cartella]);
    }

    public function pratiche(Request $request): JsonResponse
    {
        $cf = $request->attributes->get('utente')->codice_fiscale;

        return response()->json([
            'elementi' => Pratica::where('codice_fiscale', $cf)->orderByDesc('aperta_il')->get(),
        ]);
    }

    public function pratica(Request $request, int $id): JsonResponse
    {
        $cf = $request->attributes->get('utente')->codice_fiscale;
        $pratica = Pratica::where('codice_fiscale', $cf)->with([
            'note' => fn ($q) => $q->where('visibile_al_cittadino', true)->orderBy('creata_il'),
            'allegati',
        ])->find($id);

        if ($pratica === null) {
            return response()->json(['errore' => 'Pratica non trovata.'], 404);
        }

        return response()->json(['pratica' => $pratica]);
    }

    public function rimborsi(Request $request): JsonResponse
    {
        $cf = $request->attributes->get('utente')->codice_fiscale;

        return response()->json([
            'elementi' => Rimborso::where('codice_fiscale', $cf)->orderByDesc('richiesto_il')->get(),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['stato' => 'ok', 'servizio' => 'agenzia-uscite-api']);
    }
}
