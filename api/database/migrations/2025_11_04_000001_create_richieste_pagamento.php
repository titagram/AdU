<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIFETTO INTENZIONALE (L18, capitolo 6): le richieste di pagamento si duplicano.
 * La tabella esiste per far vedere il danno: due righe per un solo rimborso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('richieste_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rimborso_id')->constrained('rimborsi')->cascadeOnDelete();
            $table->decimal('importo', 12, 2);
            $table->string('iban', 34)->nullable();
            $table->timestamp('creata_il');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('richieste_pagamento');
    }
};
