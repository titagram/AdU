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
                    md5($password) === $utente->password,
                    "I3: {$utente->email} usa una password del dizionario"
                );
            }
        }
    }

    /**
     * L22 — enumerazione degli utenti.
     *
     * Questo test è scritto al contrario di come dovrebbe essere, e lo è apposta: descrive il
     * difetto introdotto nel capitolo 2, così il diff fra il test corretto e questo racconta
     * da solo cosa è cambiato. Chi corregge il laboratorio deve vederlo fallire.
     */
    public function test_il_login_rivela_se_un_codice_fiscale_esiste(): void
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

        $this->assertNotSame(
            $inesistente->json('errore'),
            $sbagliata->json('errore'),
            'L22: i due errori sono distinguibili, e questo è il difetto da trovare'
        );
    }

    /**
     * L01: il difetto è voluto. Quando questo test fu scritto (M1) il portale era ancora
     * protetto; dal capitolo 3 la cartella altrui si legge, e il test deve descrivere il
     * laboratorio per come è, altrimenti non difende niente.
     */
    public function test_la_cartella_di_un_altro_contribuente_e_leggibile_l01(): void
    {
        $token = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'ciao123',
        ])->json('token');

        $altrui = \App\Models\Cartella::where('codice_fiscale', '!=', 'NREGLI90A41H501G')->firstOrFail();

        $risposta = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/cartelle/{$altrui->id}")
            ->assertOk();

        $this->assertSame(
            $altrui->codice_fiscale,
            $risposta->json('cartella.codice_fiscale'),
            'L01: la cartella letta è di un altro contribuente, ed è questo il difetto'
        );
    }

    /**
     * L09: il difetto è voluto. Il logout non revoca il token, che non ha nemmeno una scadenza:
     * è la ragione per cui la XSS del capitolo 4 vale per sempre.
     */
    public function test_il_logout_non_revoca_il_token_l09(): void
    {
        $token = $this->postJson('/api/login', [
            'codice_fiscale' => 'NREGLI90A41H501G',
            'password' => 'ciao123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk();
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
