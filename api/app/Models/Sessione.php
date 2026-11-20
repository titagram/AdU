<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sessione extends Model
{
    protected $table = 'sessioni';

    protected $fillable = ['utente_id', 'jti', 'emesso_il', 'scadenza', 'revocato'];

    protected $casts = [
        'emesso_il' => 'datetime',
        'scadenza' => 'datetime',
        'revocato' => 'boolean',
    ];

    public function utente(): BelongsTo
    {
        return $this->belongsTo(Utente::class, 'utente_id');
    }
}
