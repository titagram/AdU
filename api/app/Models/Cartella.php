<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cartella extends Model
{
    protected $table = 'cartelle';

    protected $fillable = [
        'codice_fiscale', 'protocollo', 'importo', 'anno', 'stato', 'scadenza',
    ];

    protected $casts = [
        'importo' => 'decimal:2',
        'scadenza' => 'date',
    ];
}
