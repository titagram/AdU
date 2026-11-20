<?php

namespace Database\Seeders;

use App\Support\CodiceFiscale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder deterministico.
 *
 * Il seme è fisso: due esecuzioni producono lo stesso database, quindi i riferimenti nelle
 * schede dei laboratori (protocolli, codici fiscali, id) restano validi a ogni `lab:reset`.
 *
 * Invarianti rispettate qui, non a valle:
 *   I1 — la prima riga di `utenti` è un cittadino (Mario Rossi), non un amministratore;
 *   I3 — le password deboli sono dei cittadini; il personale interno ha password conformi;
 *   I5 — l'account amministrativo nasce con `reset_disabilitato`.
 */
class DatabaseSeeder extends Seeder
{
    private const SEME = 20251103;

    /** Password dell'account dimostrativo (Giulia Neri) e del cittadino della prima riga. */
    public const PASSWORD_DIMOSTRATIVA = 'ciao123';

    public const PASSWORD_PRIMA_RIGA = 'password';

    /** Elenco delle password deboli dei cittadini: è il dizionario del corso. */
    private const DIZIONARIO = [
        'ciao123', 'password', '123456', 'juventus', 'milan', 'napoli', 'famiglia',
        'solemare', 'andrea85', 'giulia90', 'roma2025', 'napoli1926', 'amoremio',
        'casa2024', 'vacanza', 'genova', 'torino', 'matteo', 'chiara', 'stella',
    ];

    private const NOMI_M = [
        'Mario', 'Luca', 'Andrea', 'Stefano', 'Paolo', 'Marco', 'Giovanni', 'Alessandro',
        'Davide', 'Simone', 'Federico', 'Matteo', 'Riccardo', 'Fabio', 'Lorenzo',
        'Giorgio', 'Antonio', 'Roberto', 'Massimo', 'Silvio', 'Daniele', 'Emanuele',
        'Vincenzo', 'Salvatore', 'Pietro', 'Carlo', 'Enrico', 'Nicola', 'Tommaso', 'Samuele',
    ];

    private const NOMI_F = [
        'Giulia', 'Chiara', 'Sara', 'Elena', 'Francesca', 'Valentina', 'Martina',
        'Alessia', 'Silvia', 'Laura', 'Federica', 'Anna', 'Paola', 'Roberta', 'Beatrice',
        'Camilla', 'Ilaria', 'Serena', 'Monica', 'Cristina', 'Daniela', 'Erica',
        'Greta', 'Letizia', 'Marta', 'Noemi', 'Rebecca', 'Sofia', 'Teresa', 'Viola',
    ];

    private const COGNOMI = [
        'Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo', 'Ricci',
        'Marino', 'Greco', 'Bruno', 'Gallo', 'Conti', 'De Luca', 'Mancini', 'Costa',
        'Giordano', 'Rizzo', 'Lombardi', 'Moretti', 'Barbieri', 'Fontana', 'Santoro',
        'Mariani', 'Rinaldi', 'Caruso', 'Ferrara', 'Galli', 'Martini', 'Leone',
        'Longo', 'Gentile', 'Martinelli', 'Vitale', 'Serra', 'Coppola', 'De Santis',
        'D\'Angelo', 'Marchetti', 'Parisi', 'Villa', 'Conte', 'Ferraro', 'Ferri', 'Fabbri',
    ];

    private const COMUNI = [
        ['Roma', 'H501'], ['Milano', 'F205'], ['Napoli', 'F839'], ['Torino', 'L219'],
        ['Bologna', 'A944'], ['Firenze', 'D612'], ['Genova', 'D969'], ['Palermo', 'G273'],
        ['Bari', 'A662'], ['Verona', 'L781'], ['Padova', 'G224'], ['Catania', 'C351'],
    ];

    private const VIE = [
        'Via Garibaldi', 'Via Mazzini', 'Via Roma', 'Corso Italia', 'Via Dante',
        'Via Cavour', 'Viale Europa', 'Via Verdi', 'Piazza Vittorio Emanuele',
        'Via delle Fornaci', 'Via dei Mille', 'Corso Vittorio Emanuele',
    ];

    private const SERVIZI = [
        'Rimborso IRPEF', 'Dichiarazione 730', 'Modello F24', 'Certificazione unica',
        'Sportello appuntamento', 'Agevolazioni prima casa',
    ];

    public function run(): void
    {
        mt_srand(self::SEME);

        $adesso = now();

        // --- 1. Mario Rossi: prima riga, cittadino. Invariante I1. -------------------------
        $mario = $this->creaContribuente('Mario', 'Rossi', 'M', '1985-08-01', self::PASSWORD_PRIMA_RIGA);
        $marioId = $this->creaUtente($mario, 'cittadino', self::PASSWORD_PRIMA_RIGA, false);

        // --- 2. Giulia Neri: account dimostrativo del corso. ------------------------------
        $giulia = $this->creaContribuente('Giulia', 'Neri', 'F', '1990-01-01', self::PASSWORD_DIMOSTRATIVA, ['Roma', 'H501']);
        $giulia['personaggio'] = 'giulia';
        $this->creaUtente($giulia, 'cittadino', self::PASSWORD_DIMOSTRATIVA, false);

        // La scheda account consegnata agli studenti riporta questo indirizzo: se il seed ne
        // inventasse un altro, il primo controllo che fanno sarebbe una smentita.
        DB::table('contribuenti')->where('codice_fiscale', $giulia['codice_fiscale'])->update([
            'indirizzo' => 'Via delle Fornaci 14',
            'cap' => '00165',
            'provincia' => 'RM',
        ]);

        DB::table('cartelle')->insert([
            'codice_fiscale' => $giulia['codice_fiscale'],
            'protocollo' => '2025/000481',
            'importo' => 1247.00,
            'anno' => 2025,
            'stato' => 'in_sospeso',
            'scadenza' => '2025-11-30',
            'created_at' => $adesso, 'updated_at' => $adesso,
        ]);

        // --- 3. Personale interno: password conformi, nessuna nel dizionario. ------------
        $sara = $this->creaContribuente('Sara', 'Bianchi', 'F', '1979-03-14', null);
        $saraId = $this->creaUtente($sara, 'funzionario', 'Bianchi!2025#Sportello', false);

        foreach ([[0, 'Paolo', 'Gentile', 'M'], [1, 'Elena', 'Marchetti', 'F']] as [$i, $n, $c, $s]) {
            $f = $this->creaContribuente($n, $c, $s, sprintf('198%d-0%d-1%d', 2 + $i, 4 + $i, 2 + $i), null);
            $this->creaUtente($f, 'funzionario', 'Sportello!2025#'.($i + 2), false);
        }

        // --- 4. Amministratore: reset disabilitato. Invariante I5. ----------------------
        $admin = $this->creaContribuente('Aldo', 'Marchetti', 'M', '1965-11-02', null);
        $this->creaUtente($admin, 'amministratore', 'Amministratore!2025#Sicuro', true);

        // --- 5. Gli altri 198 cittadini. ------------------------------------------------
        $cittadini = [$giulia, $sara];

        for ($i = 0; $i < 198; $i++) {
            $sesso = mt_rand(0, 1) === 0 ? 'M' : 'F';
            $nome = $sesso === 'M'
                ? self::NOMI_M[mt_rand(0, count(self::NOMI_M) - 1)]
                : self::NOMI_F[mt_rand(0, count(self::NOMI_F) - 1)];
            $cognome = self::COGNOMI[mt_rand(0, count(self::COGNOMI) - 1)];
            $nascita = sprintf('%04d-%02d-%02d', mt_rand(1950, 2004), mt_rand(1, 12), mt_rand(1, 28));

            $cittadini[] = $this->creaContribuente($nome, $cognome, $sesso, $nascita, null);
        }

        foreach (array_slice($cittadini, 2) as $c) {
            $password = self::DIZIONARIO[mt_rand(0, count(self::DIZIONARIO) - 1)];
            $this->creaUtente($c, 'cittadino', $password, false);
        }

        // --- 6. Posizione fiscale e pratiche. ------------------------------------------
        $this->seminaPosizioneFiscale($cittadini, $adesso);

        // --- 7. La pratica "riservata" di Sara: colpo di scena del capitolo 3. --------
        $this->seminaPraticaRiservata($sara, $saraId, $adesso);

        // --- 8. Log plausibili. -------------------------------------------------------
        $this->seminaLog($cittadini, $adesso);
    }

    /** @return array<string, mixed> */
    private function creaContribuente(
        string $nome,
        string $cognome,
        string $sesso,
        string $nascita,
        ?string $passwordChiara,
        ?array $luogo = null,
    ): array {
        // Il luogo può essere imposto: i personaggi degli artefatti devono avere il codice
        // fiscale che compare nei documenti, altrimenti il primo studente che controlla
        // scopre che la scheda account racconta un'altra persona.
        [$comune, $codice] = $luogo ?? self::COMUNI[mt_rand(0, count(self::COMUNI) - 1)];
        $data = new \DateTimeImmutable($nascita);
        $cf = CodiceFiscale::genera($nome, $cognome, $data, $sesso, $codice);
        $slug = strtolower(str_replace([' ', "'"], ['.', ''], $nome.'.'.$cognome));

        return [
            'codice_fiscale' => $cf,
            'nome' => $nome,
            'cognome' => $cognome,
            'data_nascita' => $nascita,
            'sesso' => $sesso,
            'comune_nascita' => $comune,
            'indirizzo' => self::VIE[mt_rand(0, count(self::VIE) - 1)].' '.mt_rand(1, 120),
            'cap' => sprintf('%05d', mt_rand(10, 98).mt_rand(100, 999)),
            'provincia' => ['RM', 'MI', 'NA', 'TO', 'BO', 'FI', 'GE', 'PA', 'BA', 'VR'][mt_rand(0, 9)],
            'pec' => $slug.'@pec.example.it',
            'telefono' => '3'.mt_rand(20, 99).' '.mt_rand(1000000, 9999999),
            'password_chiara' => $passwordChiara,
        ];
    }

    private function creaUtente(array $c, string $ruolo, string $password, bool $resetDisabilitato): int
    {
        DB::table('contribuenti')->insert([
            'codice_fiscale' => $c['codice_fiscale'],
            'nome' => $c['nome'],
            'cognome' => $c['cognome'],
            'data_nascita' => $c['data_nascita'],
            'sesso' => $c['sesso'],
            'comune_nascita' => $c['comune_nascita'],
            'indirizzo' => $c['indirizzo'],
            'cap' => $c['cap'],
            'provincia' => $c['provincia'],
            'pec' => $c['pec'],
            'telefono' => $c['telefono'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Due cittadini possono avere lo stesso nome e cognome: l'indirizzo va reso unico
        // senza inventare un identificativo che poi non torni nel resto del seed.
        $base = strtolower($c['nome'].'.'.$c['cognome']);
        $email = $base.'@example.it';
        for ($n = 2; DB::table('utenti')->where('email', $email)->exists(); $n++) {
            $email = $base.$n.'@example.it';
        }

        return DB::table('utenti')->insertGetId([
            'codice_fiscale' => $c['codice_fiscale'],
            'nome' => $c['nome'],
            'cognome' => $c['cognome'],
            'email' => $email,
            // DIFETTO INTENZIONALE (L08, capitolo 2): MD5 senza sale, per compatibilità con lo
            // sportello del 2019 che verifica la password dentro la query. Il nuovo portale
            // avrebbe dovuto migrare gli hash al primo accesso, e non l'ha mai fatto.
            'password' => md5($password),
            'ruolo' => $ruolo,
            'attivo' => true,
            'reset_disabilitato' => $resetDisabilitato,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seminaPosizioneFiscale(array $cittadini, $adesso): void
    {
        $cartelle = [];
        $dichiarazioni = [];
        $pratiche = [];
        $rimborsi = [];
        $iban = [];
        $protocollo = 1;

        foreach ($cittadini as $c) {
            $cf = $c['codice_fiscale'];

            // Giulia è la persona della scheda account: i suoi numeri sono fissi e devono
            // coincidere con quelli del documento (2 dichiarazioni, 1 pratica, 1 rimborso).
            if (($c['personaggio'] ?? null) === 'giulia') {
                $dichiarazioni[] = [
                    'codice_fiscale' => $cf, 'anno' => 2024, 'reddito' => 21500,
                    'imposta' => 4750, 'stato' => 'liquidata',
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
                $dichiarazioni[] = [
                    'codice_fiscale' => $cf, 'anno' => 2023, 'reddito' => 20400,
                    'imposta' => 4412, 'stato' => 'archiviata',
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
                $pratiche[] = [
                    'protocollo' => '2025/004102',
                    'codice_fiscale' => $cf,
                    'servizio' => 'Rimborso IRPEF',
                    'stato' => 'in_lavorazione',
                    'aperta_il' => '2025-09-02',
                    'funzionario_id' => null,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
                $rimborsi[] = [
                    'codice_fiscale' => $cf, 'dichiarazione_id' => null,
                    'importo' => 312.40, 'iban' => null, 'stato' => 'approvato',
                    'richiesto_il' => '2025-09-05',
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
                $iban[] = [
                    'codice_fiscale' => $cf,
                    'iban' => 'IT60X0542811101000000123456',
                    'intestatario' => 'GIULIA NERI',
                    'verificato' => true,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];

                continue;
            }

            for ($a = 0; $a < mt_rand(1, 2); $a++) {
                $anno = mt_rand(2022, 2025);
                $reddito = mt_rand(8000, 45000);
                $dichiarazioni[] = [
                    'codice_fiscale' => $cf,
                    'anno' => $anno,
                    'reddito' => $reddito,
                    'imposta' => round($reddito * mt_rand(1800, 2700) / 10000, 2),
                    'stato' => ['presentata', 'liquidata'][mt_rand(0, 1)],
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
            }

            if (mt_rand(1, 3) === 1) {
                $cartelle[] = [
                    'codice_fiscale' => $cf,
                    'protocollo' => sprintf('2025/%07d', 1000 + $protocollo++),
                    'importo' => round(mt_rand(80, 3200) + mt_rand(0, 99) / 100, 2),
                    'anno' => 2025,
                    'stato' => mt_rand(1, 10) <= 3 ? 'pagata' : 'in_sospeso',
                    'scadenza' => '2025-11-30',
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
            }

            for ($p = 0; $p < mt_rand(2, 4); $p++) {
                $pratiche[] = [
                    'protocollo' => sprintf('2025/%07d', 100000 + $protocollo++),
                    'codice_fiscale' => $cf,
                    'servizio' => self::SERVIZI[mt_rand(0, count(self::SERVIZI) - 1)],
                    'stato' => ['aperta', 'in_lavorazione', 'chiusa'][mt_rand(0, 2)],
                    'aperta_il' => sprintf('2025-%02d-%02d', mt_rand(1, 10), mt_rand(1, 28)),
                    'funzionario_id' => null, // assegnato dopo l'inserimento
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
            }

            for ($r = 0; $r < mt_rand(1, 3); $r++) {
                $rimborsi[] = [
                    'codice_fiscale' => $cf,
                    'dichiarazione_id' => null,
                    'importo' => round(mt_rand(120, 2400) + mt_rand(0, 99) / 100, 2),
                    'iban' => null,
                    'stato' => ['richiesto', 'approvato', 'pagato'][mt_rand(0, 2)],
                    'richiesto_il' => sprintf('2025-%02d-%02d', mt_rand(1, 11), mt_rand(1, 28)),
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
            }

            $iban[] = [
                'codice_fiscale' => $cf,
                'iban' => 'IT'.mt_rand(10, 99).'X'.sprintf('%018d', mt_rand(0, 999999999999999999)),
                'intestatario' => strtoupper($c['nome'].' '.$c['cognome']),
                'verificato' => mt_rand(0, 1) === 1,
                'created_at' => $adesso, 'updated_at' => $adesso,
            ];
        }

        foreach (array_chunk($dichiarazioni, 200) as $chunk) {
            DB::table('dichiarazioni')->insert($chunk);
        }
        foreach (array_chunk($cartelle, 200) as $chunk) {
            DB::table('cartelle')->insert($chunk);
        }
        foreach (array_chunk($rimborsi, 200) as $chunk) {
            DB::table('rimborsi')->insert($chunk);
        }

        // DIFETTO INTENZIONALE (L17): quattro cifre, ricavabili dall'identificativo del rimborso.
        DB::table('rimborsi')->update(['otp' => DB::raw("LPAD((id * 7) % 10000, 4, '0')")]);
        foreach (array_chunk($iban, 200) as $chunk) {
            DB::table('iban')->insert($chunk);
        }
        foreach (array_chunk($pratiche, 200) as $chunk) {
            DB::table('pratiche')->insert($chunk);
        }

        // Allegato dimostrativo per il laboratorio L04: un file con una stringa nota, accanto
        // al quale la path traversal del capitolo 3 deve poter leggere altro.
        $praticaGiulia = DB::table('pratiche')->where('codice_fiscale', 'NREGLI90A41H501G')->value('id');
        $cartellaAllegati = storage_path('app/allegati/'.$praticaGiulia);
        if (! is_dir($cartellaAllegati)) {
            mkdir($cartellaAllegati, 0777, true);
        }
        // Il seed gira come root, Apache scrive come www-data: senza questo, il caricamento
        // degli allegati del capitolo 3 non riesce. Permessi larghi, come capita nei laboratori.
        @chmod(dirname($cartellaAllegati), 0777);
        @chmod($cartellaAllegati, 0777);
        file_put_contents(
            $cartellaAllegati.'/ricevuta.pdf',
            "Allegato dimostrativo della pratica {$praticaGiulia}.\n"
            ."Contenuto fittizio: serve a provare che lo scaricamento funziona.\n",
        );

        // Assegna un funzionario a ogni pratica e qualche nota.
        $funzionari = DB::table('utenti')->where('ruolo', 'funzionario')->pluck('id')->all();
        $note = [];

        foreach (DB::table('pratiche')->pluck('id') as $id) {
            DB::table('pratiche')->where('id', $id)->update([
                'funzionario_id' => $funzionari[mt_rand(0, count($funzionari) - 1)],
            ]);

            if (mt_rand(1, 4) === 1) {
                $note[] = [
                    'pratica_id' => $id,
                    'autore_id' => $funzionari[mt_rand(0, count($funzionari) - 1)],
                    'corpo' => "Documentazione acquisita. In attesa di riscontro dall'ufficio competente.",
                    'visibile_al_cittadino' => true,
                    'creata_il' => $adesso,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ];
            }
        }

        foreach (array_chunk($note, 200) as $chunk) {
            DB::table('note_pratiche')->insert($chunk);
        }
    }

    private function seminaPraticaRiservata(array $sara, int $saraId, $adesso): void
    {
        $praticaId = DB::table('pratiche')->insertGetId([
            'protocollo' => '2025/009001',
            'codice_fiscale' => $sara['codice_fiscale'],
            'servizio' => 'Agevolazioni prima casa',
            'stato' => 'in_lavorazione',
            'aperta_il' => '2025-09-12',
            'funzionario_id' => $saraId,
            'created_at' => $adesso, 'updated_at' => $adesso,
        ]);

        DB::table('cartelle')->insert([
            'codice_fiscale' => $sara['codice_fiscale'],
            'protocollo' => '2025/000902',
            'importo' => 320.50,
            'anno' => 2025,
            'stato' => 'in_sospeso',
            'scadenza' => '2025-12-15',
            'created_at' => $adesso, 'updated_at' => $adesso,
        ]);

        File::ensureDirectoryExists(storage_path('app/allegati'));
        $riservato = storage_path('app/allegati/istruttoria-riservata.pdf');
        File::put($riservato, $this->pdfMinimo(
            'ISTRUTTORIA RISERVATA - Protocollo 2025/009001',
            "Documento acquisito nell'ambito del procedimento. Non divulgabile.",
        ));

        $allegatoId = DB::table('allegati')->insertGetId([
            'pratica_id' => $praticaId,
            'nome_file' => 'istruttoria-riservata.pdf',
            'percorso' => 'allegati/istruttoria-riservata.pdf',
            'mime' => 'application/pdf',
            'dimensione' => File::size($riservato),
            'caricato_il' => $adesso,
            'created_at' => $adesso, 'updated_at' => $adesso,
        ]);

        DB::table('note_pratiche')->insert([
            'pratica_id' => $praticaId,
            'autore_id' => $saraId,
            'corpo' => 'Allegata istruttoria riservata (all. '.$allegatoId.'). Non trasmettere al richiedente.',
            'visibile_al_cittadino' => false,
            'creata_il' => $adesso,
            'created_at' => $adesso, 'updated_at' => $adesso,
        ]);
    }

    private function seminaLog(array $cittadini, $adesso): void
    {
        $righe = [];

        for ($i = 0; $i < 300; $i++) {
            $c = $cittadini[mt_rand(0, count($cittadini) - 1)];
            $righe[] = [
                'avvenuto_il' => $adesso->copy()->subMinutes(mt_rand(1, 43000)),
                'codice_fiscale' => $c['codice_fiscale'],
                'ip' => '172.19.0.'.mt_rand(2, 12),
                'metodo' => ['GET', 'GET', 'GET', 'POST'][mt_rand(0, 3)],
                'path' => ['/api/cartelle', '/api/pratiche', '/api/rimborsi', '/api/me'][mt_rand(0, 3)],
                'status' => [200, 200, 200, 404][mt_rand(0, 3)],
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/128.0',
            ];
        }

        foreach (array_chunk($righe, 200) as $chunk) {
            DB::table('log_accessi')->insert($chunk);
        }
    }

    /**
     * PDF minimo valido: serve solo a rendere credibile l'allegato "riservato".
     * Calcola gli offset reali, perché un PDF malformato non si apre e la demo perde effetto.
     */
    private function pdfMinimo(string $titolo, string $corpo): string
    {
        $contenuto = 'BT /F1 14 Tf 60 780 Td ('.$this->escapePdf($titolo).') Tj ET'."\n"
            .'BT /F1 10 Tf 60 750 Td ('.$this->escapePdf($corpo).') Tj ET';

        $oggetti = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($contenuto)." >>\nstream\n".$contenuto."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offset = [];

        foreach ($oggetti as $i => $corpoOggetto) {
            $offset[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$corpoOggetto."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($oggetti) + 1)."\n0000000000 65535 f \n";

        foreach ($offset as $posizione) {
            $pdf .= sprintf("%010d 00000 n \n", $posizione);
        }

        $pdf .= 'trailer'."\n<< /Size ".(count($oggetti) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $pdf;
    }

    private function escapePdf(string $testo): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $testo);
    }
}
