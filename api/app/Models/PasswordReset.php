<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_reset';

    protected $fillable = ['codice_fiscale', 'token', 'scadenza', 'usato'];

    protected $casts = ['scadenza' => 'datetime', 'usato' => 'boolean'];
}
