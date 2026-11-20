<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Riporta il laboratorio allo stato iniziale.
 *
 * È il comando che il docente lancia fra una lezione e l'altra: azzera il database,
 * ricarica il seed deterministico e cancella gli allegati caricati dagli studenti, così
 * i riferimenti delle schede tornano validi.
 */
class LabReset extends Command
{
    protected $signature = 'lab:reset {--force : non chiedere conferma}';

    protected $description = 'Azzera e ripopola il laboratorio';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Azzero il database del laboratorio?')) {
            return self::FAILURE;
        }

        File::deleteDirectory(storage_path('app/allegati'));
        File::ensureDirectoryExists(storage_path('app/allegati'));

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->output->write(Artisan::output());

        Artisan::call('db:seed', ['--force' => true]);
        $this->output->write(Artisan::output());

        $this->info('laboratorio ripristinato');

        return self::SUCCESS;
    }
}
