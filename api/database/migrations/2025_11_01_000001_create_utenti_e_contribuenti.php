<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafica e autenticazione.
 *
 * Nota di progetto (docs/DESIGN.md §9): la prima riga di `utenti` deve essere un cittadino,
 * non un amministratore. È l'invariante I1 e il seeder la rispetta per costruzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utenti', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16)->unique();
            $table->string('nome', 60);
            $table->string('cognome', 60);
            $table->string('email', 120)->unique();
            $table->string('password', 255);
            $table->enum('ruolo', ['cittadino', 'funzionario', 'amministratore'])->default('cittadino');
            $table->boolean('attivo')->default(true);
            $table->boolean('reset_disabilitato')->default(false);
            $table->timestamp('ultimo_accesso')->nullable();
            $table->timestamps();

            $table->index('ruolo');
        });

        Schema::create('contribuenti', function (Blueprint $table) {
            $table->string('codice_fiscale', 16)->primary();
            $table->string('nome', 60);
            $table->string('cognome', 60);
            $table->date('data_nascita');
            $table->char('sesso', 1);
            $table->string('comune_nascita', 80);
            $table->string('indirizzo', 120);
            $table->char('cap', 5);
            $table->string('provincia', 4);
            $table->string('pec', 120);
            $table->string('telefono', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribuenti');
        Schema::dropIfExists('utenti');
    }
};
