<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAccesso extends Model
{
    protected $table = 'log_accessi';

    public $timestamps = false;

    protected $fillable = ['avvenuto_il', 'codice_fiscale', 'ip', 'metodo', 'path', 'status', 'user_agent'];

    protected $casts = ['avvenuto_il' => 'datetime'];
}
