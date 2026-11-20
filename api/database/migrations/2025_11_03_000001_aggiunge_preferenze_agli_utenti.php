<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIFETTO INTENZIONALE (L23, capitolo 3): le preferenze della SPA sono un blob PHP
 * serializzato, salvato così com'arriva e riletto con `unserialize`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->text('preferenze')->nullable()->after('reset_disabilitato');
        });
    }

    public function down(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->dropColumn('preferenze');
        });
    }
};
