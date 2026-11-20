<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allegato extends Model
{
    protected $table = 'allegati';

    protected $fillable = ['pratica_id', 'nome_file', 'percorso', 'mime', 'dimensione', 'caricato_il'];

    protected $casts = ['caricato_il' => 'datetime'];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }
}
