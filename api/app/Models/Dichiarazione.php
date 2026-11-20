<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dichiarazione extends Model
{
    protected $table = 'dichiarazioni';

    protected $fillable = ['codice_fiscale', 'anno', 'reddito', 'imposta', 'stato'];

    protected $casts = ['reddito' => 'decimal:2', 'imposta' => 'decimal:2'];
}
