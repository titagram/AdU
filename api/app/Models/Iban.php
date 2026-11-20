<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Iban extends Model
{
    protected $table = 'iban';

    protected $fillable = ['codice_fiscale', 'iban', 'intestatario', 'verificato'];

    protected $casts = ['verificato' => 'boolean'];
}
