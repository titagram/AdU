<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIFETTO INTENZIONALE (L17, capitolo 2): il codice di conferma per cambiare l'IBAN è di
 * quattro cifre e vive accanto al dato che protegge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rimborsi', function (Blueprint $table) {
            $table->char('otp', 4)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('rimborsi', function (Blueprint $table) {
            $table->dropColumn('otp');
        });
    }
};
