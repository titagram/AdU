<?php

namespace App\Console\Commands;

use App\Models\Utente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Popola il database solo se è vuoto. Gira all'avvio del container, così il primo
 * `docker compose up` produce già un portale utilizzabile.
 */
class AduInstalla extends Command
{
    protected $signature = 'adu:installa';

    protected $description = 'Esegue il seed iniziale se il database è vuoto';

    public function handle(): int
    {
        if (Utente::count() > 0) {
            $this->line('database già popolato: nessuna azione');

            return self::SUCCESS;
        }

        $this->line('database vuoto: eseguo il seed');
        Artisan::call('db:seed', ['--force' => true]);
        $this->output->write(Artisan::output());

        return self::SUCCESS;
    }
}
