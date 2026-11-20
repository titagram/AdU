<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posizione fiscale: cartelle (debiti), dichiarazioni, rimborsi, IBAN.
 *
 * `cartelle` è il bersaglio dell'obiettivo dichiarato del corso: è la tabella che lo studente
 * vuole azzerare (L02) e che l'attaccante del capitolo 6 svuota (query impilata).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartelle', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->string('protocollo', 20)->unique();
            $table->decimal('importo', 10, 2);
            $table->smallInteger('anno');
            $table->enum('stato', ['in_sospeso', 'pagata', 'annullata'])->default('in_sospeso');
            $table->date('scadenza');
            $table->timestamps();

            $table->index(['codice_fiscale', 'stato']);
        });

        Schema::create('dichiarazioni', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->smallInteger('anno');
            $table->decimal('reddito', 12, 2);
            $table->decimal('imposta', 12, 2);
            $table->enum('stato', ['presentata', 'liquidata', 'archiviata'])->default('presentata');
            $table->timestamps();

            $table->index('codice_fiscale');
        });

        Schema::create('rimborsi', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->foreignId('dichiarazione_id')->nullable()->constrained('dichiarazioni')->nullOnDelete();
            $table->decimal('importo', 10, 2);
            $table->string('iban', 34)->nullable();
            $table->enum('stato', ['richiesto', 'approvato', 'pagato', 'annullato'])->default('richiesto');
            $table->date('richiesto_il')->nullable();
            $table->timestamps();

            $table->index(['codice_fiscale', 'stato']);
        });

        Schema::create('iban', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->string('iban', 34);
            $table->string('intestatario', 120);
            $table->boolean('verificato')->default(false);
            $table->timestamps();

            $table->index('codice_fiscale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iban');
        Schema::dropIfExists('rimborsi');
        Schema::dropIfExists('dichiarazioni');
        Schema::dropIfExists('cartelle');
    }
};
