<?php

namespace App\Console\Commands;

use App\Models\Pratica;
use App\Models\Utente;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fotografia del database: serve all'avvio del container e al docente prima della lezione.
 */
class AduStato extends Command
{
    protected $signature = 'adu:stato';

    protected $description = 'Mostra lo stato del database del portale';

    public function handle(): int
    {
        try {
            $utenti = Utente::count();
            $pratiche = Pratica::count();
        } catch (Throwable $e) {
            $this->error('database non raggiungibile: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['tabella', 'righe'], [
            ['utenti', $utenti],
            ['contribuenti', \App\Models\Contribuente::count()],
            ['cartelle', \App\Models\Cartella::count()],
            ['pratiche', $pratiche],
            ['rimborsi', \App\Models\Rimborso::count()],
            ['log_accessi', \App\Models\LogAccesso::count()],
        ]);

        $prima = Utente::orderBy('id')->first();
        $this->line(sprintf('prima riga di utenti: %s %s (%s)', $prima?->nome, $prima?->cognome, $prima?->ruolo));

        return self::SUCCESS;
    }
}
