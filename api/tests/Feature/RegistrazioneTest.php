<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La registrazione esiste, ma non è una scorciatoia per la scala dei privilegi.
 *
 * Il test difende la scelta: il ruolo lo decide il server. Se un giorno qualcuno passasse
 * `$request->all()` al modello, qui si romperebbe — ed è il momento giusto per accorgersene.
 */
class RegistrazioneTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function dati(array $sovrascritture = []): array
    {
        return array_merge([
            'nome' => 'Prova',
            'cognome' => 'Cittadino',
            'codice_fiscale' => 'PRVCTT90A01H501Z',
            'email' => 'prova.cittadino@example.it',
            'data_nascita' => '1990-01-01',
            'sesso' => 'M',
            'comune_nascita' => 'Roma',
            'indirizzo' => 'Via di Prova 7',
            'cap' => '00165',
            'provincia' => 'RM',
            'password' => 'password123',
        ], $sovrascritture);
    }

    public function test_un_cittadino_puo_registrarsi_e_accedere(): void
    {
        $this->postJson('/api/registrazione', $this->dati())
            ->assertCreated()
            ->assertJsonPath('utente.codice_fiscale', 'PRVCTT90A01H501Z');

        $this->postJson('/api/login', [
            'codice_fiscale' => 'PRVCTT90A01H501Z',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token', 'utente']);
    }

    public function test_il_ruolo_non_si_puo_scegliere_alla_registrazione(): void
    {
        $this->postJson('/api/registrazione', $this->dati(['ruolo' => 'amministratore']))
            ->assertCreated();

        $this->assertDatabaseHas('utenti', [
            'codice_fiscale' => 'PRVCTT90A01H501Z',
            'ruolo' => 'cittadino',
        ]);
    }

    public function test_un_codice_fiscale_gia_registrato_viene_rifiutato(): void
    {
        // La prima riga del seed è Mario Rossi: è già registrato.
        $this->postJson('/api/registrazione', $this->dati([
            'codice_fiscale' => 'RSSMRA85M01F205T',
        ]))->assertStatus(409);
    }

    public function test_un_codice_fiscale_malformato_non_passa(): void
    {
        $this->postJson('/api/registrazione', $this->dati([
            'codice_fiscale' => 'ABC',
        ]))->assertStatus(422);
    }
}
