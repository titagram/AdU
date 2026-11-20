<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicurezza: OTP, token di reset, sessioni (revoca dei JWT), log degli accessi.
 *
 * `sessioni` è ciò che permette a un logout di essere vero. In M2 il corso insegnerà cosa
 * succede quando questa tabella smette di essere consultata (L09, L22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->char('codice', 6);
            $table->string('scopo', 40);
            $table->timestamp('scadenza');
            $table->boolean('usato')->default(false);
            $table->timestamps();

            $table->index(['codice_fiscale', 'scopo']);
        });

        Schema::create('password_reset', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16);
            $table->string('token', 64)->index();
            $table->timestamp('scadenza');
            $table->boolean('usato')->default(false);
            $table->timestamps();
        });

        Schema::create('sessioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utente_id')->constrained('utenti')->cascadeOnDelete();
            $table->string('jti', 36)->unique();
            $table->timestamp('emesso_il');
            $table->timestamp('scadenza');
            $table->boolean('revocato')->default(false);
            $table->timestamps();
        });

        Schema::create('log_accessi', function (Blueprint $table) {
            $table->id();
            $table->timestamp('avvenuto_il');
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('metodo', 8);
            $table->string('path', 255);
            $table->smallInteger('status');
            $table->text('user_agent')->nullable();

            $table->index('avvenuto_il');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_accessi');
        Schema::dropIfExists('sessioni');
        Schema::dropIfExists('password_reset');
        Schema::dropIfExists('otp');
    }
};
