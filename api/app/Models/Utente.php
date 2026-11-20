<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Utente extends Model
{
    use HasFactory;

    protected $table = 'utenti';

    protected $fillable = [
        'codice_fiscale', 'nome', 'cognome', 'email', 'password',
        'ruolo', 'attivo', 'reset_disabilitato', 'ultimo_accesso',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'attivo' => 'boolean',
        'reset_disabilitato' => 'boolean',
        'ultimo_accesso' => 'datetime',
    ];

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'funzionario_id');
    }

    public function sessioni(): HasMany
    {
        return $this->hasMany(Sessione::class, 'utente_id');
    }

    public function isStaff(): bool
    {
        return in_array($this->ruolo, ['funzionario', 'amministratore'], true);
    }
}
