<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recuperi_password', function (Blueprint $table) {
            $table->id();
            $table->string('codice_fiscale', 16)->index();
            $table->string('token', 64);
            $table->dateTime('scadenza');
            $table->boolean('usato')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuperi_password');
    }
};
