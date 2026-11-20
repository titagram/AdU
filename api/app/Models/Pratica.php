<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pratica extends Model
{
    protected $table = 'pratiche';

    protected $fillable = [
        'protocollo', 'codice_fiscale', 'servizio', 'stato', 'aperta_il', 'funzionario_id',
    ];

    protected $casts = ['aperta_il' => 'date'];

    public function note(): HasMany
    {
        return $this->hasMany(NotaPratica::class);
    }

    public function allegati(): HasMany
    {
        return $this->hasMany(Allegato::class);
    }

    public function funzionario(): BelongsTo
    {
        return $this->belongsTo(Utente::class, 'funzionario_id');
    }
}
