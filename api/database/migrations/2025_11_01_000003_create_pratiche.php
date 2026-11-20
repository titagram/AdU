<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pratiche, note e allegati.
 *
 * `note_pratiche` è scritta dal cittadino (integrazione alla pratica) e letta dal funzionario
 * nel modulo sportello: è il tramite dell'XSS stored (L15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pratiche', function (Blueprint $table) {
            $table->id();
            $table->string('protocollo', 20)->unique();
            $table->string('codice_fiscale', 16);
            $table->string('servizio', 80);
            $table->enum('stato', ['aperta', 'in_lavorazione', 'chiusa'])->default('aperta');
            $table->date('aperta_il');
            $table->foreignId('funzionario_id')->nullable()->constrained('utenti')->nullOnDelete();
            $table->timestamps();

            $table->index(['codice_fiscale', 'stato']);
        });

        Schema::create('note_pratiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pratica_id')->constrained('pratiche')->cascadeOnDelete();
            $table->foreignId('autore_id')->constrained('utenti')->cascadeOnDelete();
            $table->text('corpo');
            $table->boolean('visibile_al_cittadino')->default(true);
            $table->timestamp('creata_il');
            $table->timestamps();
        });

        Schema::create('allegati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pratica_id')->constrained('pratiche')->cascadeOnDelete();
            $table->string('nome_file', 160);
            $table->string('percorso', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('dimensione');
            $table->timestamp('caricato_il');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allegati');
        Schema::dropIfExists('note_pratiche');
        Schema::dropIfExists('pratiche');
    }
};
