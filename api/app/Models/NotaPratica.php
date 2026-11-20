<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaPratica extends Model
{
    protected $table = 'note_pratiche';

    protected $fillable = ['pratica_id', 'autore_id', 'corpo', 'visibile_al_cittadino', 'creata_il'];

    protected $casts = [
        'visibile_al_cittadino' => 'boolean',
        'creata_il' => 'datetime',
    ];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function autore(): BelongsTo
    {
        return $this->belongsTo(Utente::class, 'autore_id');
    }
}
