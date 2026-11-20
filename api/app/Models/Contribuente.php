<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribuente extends Model
{
    protected $table = 'contribuenti';

    protected $primaryKey = 'codice_fiscale';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'codice_fiscale', 'nome', 'cognome', 'data_nascita', 'sesso',
        'comune_nascita', 'indirizzo', 'cap', 'provincia', 'pec', 'telefono',
    ];

    protected $casts = ['data_nascita' => 'date'];
}
