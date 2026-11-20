<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cartella;
use App\Models\NotaPratica;
use App\Models\Pratica;
use App\Models\Rimborso;
use App\Models\Utente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
        // DIFETTO INTENZIONALE (L01, capitolo 3): nessun filtro sul proprietario. Il controllo
        // c'era, ed è stato tolto quando la SPA ha cominciato a chiedere la cartella per id
        // dopo il login: «tanto l'id lo conosce solo chi ha la cartella». Non è vero.
        $cartella = Cartella::find($id);

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
        // DIFETTO INTENZIONALE (L01): idem, sulla pratica.
        $pratica = Pratica::with([
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

    /** L01 — IDOR in lettura sui rimborsi: la rotta non esisteva, e ora non controlla. */
    public function rimborso(Request $request, int $id): JsonResponse
    {
        $rimborso = Rimborso::find($id);

        return $rimborso === null
            ? response()->json(['errore' => 'Rimborso non trovato.'], 404)
            : response()->json($rimborso);
    }

    /**
     * DIFETTO INTENZIONALE (L02, capitolo 3): IDOR in scrittura più assegnazione di massa.
     *
     * Il corpo della richiesta finisce direttamente nel modello: si può cambiare l'importo di
     * una cartella che non è la propria, e anche il codice fiscale a cui è intestata.
     */
    public function aggiornaCartella(Request $request, int $id): JsonResponse
    {
        $cartella = Cartella::find($id);

        if ($cartella === null) {
            return response()->json(['errore' => 'Cartella non trovata.'], 404);
        }

        $cartella->update($request->all());

        return response()->json($cartella->fresh());
    }

    /**
     * DIFETTO INTENZIONALE (L05, capitolo 3): assegnazione di massa sul profilo.
     *
     * `PATCH /api/profilo` con `{"codice_fiscale":"NREGLI90A41H501G"}` cambia l'identità fiscale
     * dell'account: da quel momento il portale mostra i dati di un'altra persona. Con `ruolo`
     * il secondo vettore è nel capitolo 6, sullo sportello.
     */
    public function aggiornaProfilo(Request $request): JsonResponse
    {
        $utente = $request->attributes->get('utente');

        $utente->update($request->all());

        return response()->json($utente->fresh());
    }

    /**
     * DIFETTO INTENZIONALE (L23, capitolo 3): `unserialize` su input non fidato.
     *
     * Le preferenze della SPA sono un blob PHP serializzato in base64. Non c'è firma, non c'è
     * lista di classi ammesse: chi costruisce l'oggetto decide cosa il server istanzia.
     */
    public function salvaPreferenze(Request $request): JsonResponse
    {
        $dati = $request->validate([
            'preferenze' => ['required', 'string'],
        ]);

        $preferenze = unserialize(base64_decode($dati['preferenze']));

        $utente = $request->attributes->get('utente');
        $utente->forceFill(['preferenze' => json_encode($preferenze)])->save();

        return response()->json(['preferenze' => $preferenze]);
    }

    /**
     * DIFETTO INTENZIONALE (L23, secondo vettore): la condizione di filtro è codice eseguito.
     *
     * L'import delle pratiche accetta una `condizione` scritta in PHP e la valuta con `eval`.
     * Serve a filtrare per stato durante la migrazione dei fascicoli: è rimasto in produzione.
     */
    public function importaPratiche(Request $request): JsonResponse
    {
        $condizione = (string) $request->input('condizione', 'true');

        $utente = $request->attributes->get('utente');

        $ammesse = [];

        DB::table('pratiche')->where('codice_fiscale', $utente->codice_fiscale)->orderBy('id')
            ->each(function ($pratica) use ($condizione, &$ammesse) {
                if (eval('return (bool) ('.$condizione.');')) {
                    $ammesse[] = $pratica->protocollo;
                }
            });

        return response()->json(['importate' => count($ammesse), 'protocolli' => $ammesse]);
    }

    /**
     * DIFETTO INTENZIONALE (L03, capitolo 3): forced browsing.
     *
     * La rotta sta sotto `/admin`, nel gruppo autenticato, ma nessuno controlla il ruolo:
     * qualunque cittadino scarica l'elenco completo dei contribuenti.
     */
    public function esportaContribuenti(): Response
    {
        $righe = Utente::orderBy('id')->get(['codice_fiscale', 'nome', 'cognome', 'email', 'ruolo']);

        $csv = "codice_fiscale;nome;cognome;email;ruolo\n";
        foreach ($righe as $r) {
            $csv .= implode(';', [$r->codice_fiscale, $r->nome, $r->cognome, $r->email, $r->ruolo])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="contribuenti.csv"',
        ]);
    }

    /**
     * DIFETTO INTENZIONALE (L04, capitolo 3): path traversal.
     *
     * Il nome del file arriva dal client e viene concatenato al percorso senza normalizzarlo:
     * `?file=../../../../.env` legge il file di configurazione dell'API.
     */
    public function scaricaAllegato(Request $request, int $id)
    {
        $pratica = Pratica::find($id);

        if ($pratica === null) {
            return response()->json(['errore' => 'Pratica non trovata.'], 404);
        }

        $file = (string) $request->query('file', '');

        $percorso = storage_path('app/allegati/'.$id.'/'.$file);

        if (! is_file($percorso)) {
            return response()->json(['errore' => 'Allegato non trovato.'], 404);
        }

        return response()->file($percorso);
    }

    /**
     * DIFETTO INTENZIONALE (L26, capitolo 3): caricamento non sicuro.
     *
     * Il tipo del file è quello dichiarato dal client nel `Content-Type`, e il nome è quello
     * originale. Un `.html` o un `.svg` caricato qui viene servito dal dominio dell'agenzia.
     */
    public function caricaAllegato(Request $request, int $id): JsonResponse
    {
        $pratica = Pratica::find($id);

        if ($pratica === null) {
            return response()->json(['errore' => 'Pratica non trovata.'], 404);
        }

        $file = $request->file('allegato');

        if ($file === null) {
            return response()->json(['errore' => 'Nessun file.'], 422);
        }

        $consentiti = ['application/pdf', 'image/png', 'image/jpeg', 'text/plain'];
        if (! in_array($file->getClientMimeType(), $consentiti, true)) {
            return response()->json(['errore' => 'Formato non ammesso.'], 422);
        }

        $cartella = storage_path('app/allegati/'.$id);
        @mkdir($cartella, 0775, true);

        $nome = $file->getClientOriginalName();
        $file->move($cartella, $nome);

        return response()->json([
            'allegato' => $nome,
            'scaricabile_da' => '/api/pratiche/'.$id.'/allegato?file='.urlencode($nome),
        ]);
    }

    /**
     * DIFETTO INTENZIONALE (L15, capitolo 4): nota scritta dal cittadino, conservata così com'è.
     *
     * Il testo non viene ripulito perché «tanto la nota la legge solo il funzionario». Il
     * funzionario la legge in due posti, e in entrambi finisce nell'HTML.
     */
    public function scriviNota(Request $request, int $id): JsonResponse
    {
        $dati = $request->validate([
            'corpo' => ['required', 'string', 'max:2000'],
        ]);

        $utente = $request->attributes->get('utente');

        $pratica = Pratica::where('codice_fiscale', $utente->codice_fiscale)->find($id);

        if ($pratica === null) {
            return response()->json(['errore' => 'Pratica non trovata.'], 404);
        }

        $nota = NotaPratica::create([
            'pratica_id' => $pratica->id,
            'autore_id' => $utente->id,
            'corpo' => $dati['corpo'],
            'visibile_al_cittadino' => true,
            'creata_il' => now(),
        ]);

        return response()->json(['nota' => $nota], 201);
    }

    /**
     * Le pratiche del funzionario, con le note dei cittadini.
     *
     * Qui il controllo di ruolo c'è davvero: è il difetto complementare a L03, per far vedere
     * che il problema del capitolo 4 non è l'accesso ai dati — è quello che i dati fanno
     * quando arrivano al browser di chi li legge.
     */
    public function praticheDelFunzionario(Request $request): JsonResponse
    {
        $utente = $request->attributes->get('utente');

        if (! in_array($utente->ruolo, ['funzionario', 'amministratore'], true)) {
            return response()->json(['errore' => 'Riservato al personale.'], 403);
        }

        $pratiche = Pratica::when(
            $utente->ruolo === 'funzionario',
            fn ($q) => $q->where('funzionario_id', $utente->id),
        )->with(['note' => fn ($q) => $q->orderBy('creata_il')])
            ->orderByDesc('aperta_il')
            ->limit(50)
            ->get();

        return response()->json(['elementi' => $pratiche]);
    }
}
