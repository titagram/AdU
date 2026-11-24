<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recuperi_password', function (Blueprint $table) {
            $table->string('token_portale_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('recuperi_password', function (Blueprint $table) {
            $table->dropUnique(['token_portale_hash']);
            $table->dropColumn('token_portale_hash');
        });
    }
};
