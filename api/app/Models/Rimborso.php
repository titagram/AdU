<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rimborso extends Model
{
    protected $table = 'rimborsi';

    protected $fillable = [
        'codice_fiscale', 'dichiarazione_id', 'importo', 'iban', 'stato', 'richiesto_il',
    ];

    protected $casts = [
        'importo' => 'decimal:2',
        'richiesto_il' => 'date',
    ];
}
