<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le invarianti del laboratorio, in forma di test.
 *
 * Questi test difendono le regole che rendono il corso sensato: se un giorno qualcuno
 * "aggiusta" il seed e mette un amministratore nella prima riga, il laboratorio perde il suo
 * significato e qui si rompe qualcosa. In M2 i test sull'autenticazione verranno riscritti
 * per descrivere il comportamento volutamente insicuro: anche quel diff è materiale del corso.
 */
class InvariantiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_la_prima_riga_di_utenti_e_un_cittadino(): void
    {
        $prima = \App\Models\Utente::orderBy('id')->firstOrFail();

        $this->assertSame('cittadino', $prima->ruolo, 'I1: la prima riga deve essere un cittadino');
        $this->assertSame('Mario', $prima->nome);
        $this->assertSame('Rossi', $prima->cognome);
    }

    public function test_l_amministratore_non_puo_reimpostare_la_password(): void
    {
        $admin = \App\Models\Utente::where('ruolo', 'amministratore')->firstOrFail();

        $this->assertTrue($admin->reset_disabilitato, 'I5: l’account amministrativo non ha reset');
    }

    public function test_le_password_del_personale_non_sono_nel_dizionario(): void
    {
        $deboli = ['ciao123', 'password', '123456', 'juventus', 'milan', 'napoli'];
        $personale = \App\Models\Utente::whereIn('ruolo', ['funzionario', 'amministratore'])->get();

        $this->assertGreaterThan(0, $personale->count());

        foreach ($personale as $utente) {
            foreach ($deboli as $password) {
                $this->assertFalse(
                    \Illuminate\Support\Facades\Hash::check($password, $utente->password),
                    "I3: {$utente->email} usa una password del dizionario"
                );
            }
        }
    }

    public function test_il_login_usa_un_solo_messaggio_per_ogni_errore(): void
    {
        $inesistente = $this->postJson('/api/login', [
            'codice_fiscale' => 'AAAAAA00A00A000A',
            'password' => 'qualunque',
        ]);

        $sbagliata = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'sbagliata',
        ]);

        $inesistente->assertStatus(401);
        $sbagliata->assertStatus(401);
        $this->assertSame(
            $inesistente->json('errore'),
            $sbagliata->json('errore'),
            'i due errori non devono essere distinguibili'
        );
    }

    public function test_la_cartella_di_un_altro_contribuente_non_e_visibile(): void
    {
        $token = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'ciao123',
        ])->json('token');

        $altrui = \App\Models\Cartella::where('codice_fiscale', '!=', 'NREGLI90A41H501G')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/cartelle/{$altrui->id}")
            ->assertStatus(404);
    }

    public function test_il_logout_revoca_il_token(): void
    {
        $token = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'ciao123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me')->assertStatus(401);
    }

    public function test_il_portale_espone_la_posizione_della_scheda_account(): void
    {
        $token = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'ciao123',
        ])->json('token');

        $cartelle = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/cartelle');

        $cartelle->assertOk()
            ->assertJsonPath('elementi.0.protocollo', '2025/000481')
            ->assertJsonPath('elementi.0.importo', '1247.00');
    }
}
