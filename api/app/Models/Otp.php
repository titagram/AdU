<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'otp';

    protected $fillable = ['codice_fiscale', 'codice', 'scopo', 'scadenza', 'usato'];

    protected $casts = ['scadenza' => 'datetime', 'usato' => 'boolean'];
}
