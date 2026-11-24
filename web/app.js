/*
 * Agenzia delle Uscite — SPA dell'area riservata.
 *
 * JavaScript senza build: moduli nativi, nessun framework. Il portale è la superficie che
 * gli studenti vedranno di più, quindi il codice resta leggibile a chi apre i sorgenti.
 *
 * M1: la SPA è scritta in modo corretto — testo sempre passato per `testo()` e mai
 * concatenato in HTML. In M4 una sola riga (`torna`) userà `innerHTML` di proposito: è il
 * laboratorio L16, e sarà visibile nel diff.
 */

// Il portale e l'API stanno su due origini diverse, come in un'installazione reale. L'indirizzo
// dell'API si ricava da quello con cui il browser ha aperto la pagina: così il laboratorio
// funziona uguale in locale, da un altro dispositivo della tailnet o da un altro nome host.
const API = `${location.protocol}//${location.hostname}:8081`;

/*
 * DIFETTO INTENZIONALE (L09, capitolo 2): la chiave condivisa con il servizio bancario.
 *
 * Serviva quando la verifica dell'IBAN la faceva il browser, chiamando direttamente il
 * servizio interno. Poi la chiamata è passata all'API — e questa riga è rimasta, perché
 * cancellarla avrebbe richiesto una nuova build e nessuno l'ha più toccata.
 *
 * Non è la chiave che firma i token: è quella che apre il servizio bancario (invariante I4).
 */
const CHIAVE_BANCA = 'banca-chiave-servizio-2025';

const CHIAVI = { token: 'adu.token', utente: 'adu.utente' };

const stato = {
    token: localStorage.getItem(CHIAVI.token) || null,
    utente: JSON.parse(localStorage.getItem(CHIAVI.utente) || 'null'),
};

// Messaggio da mostrare una volta sola: serve a spiegare perché si è finiti sulla pagina di
// accesso (sessione scaduta), invece di lasciar cadere l'utente lì senza una parola.
let avviso = null;

const euro = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
const data = new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium' });
const dataOra = new Intl.DateTimeFormat('it-IT', { dateStyle: 'short', timeStyle: 'short' });

/* --- utilità ------------------------------------------------------------------- */

function testo(valore) {
    return document.createTextNode(valore === null || valore === undefined ? '—' : String(valore));
}

function el(tag, attributi = {}, ...figli) {
    const nodo = document.createElement(tag);

    for (const [chiave, valore] of Object.entries(attributi)) {
        if (chiave === 'classe') nodo.className = valore;
        else if (chiave === 'testo') nodo.textContent = valore;
        else if (chiave.startsWith('on')) nodo.addEventListener(chiave.slice(2), valore);
        else nodo.setAttribute(chiave, valore);
    }

    for (const figlio of figli) {
        nodo.append(figlio instanceof Node ? figlio : testo(figlio));
    }

    return nodo;
}

function etichetta(valore) {
    return el('span', { classe: `etichetta ${String(valore).replace(/[^a-z_]/g, '')}`, testo: String(valore).replace(/_/g, ' ') });
}

function importo(valore) {
    return euro.format(Number(valore ?? 0));
}

function dataBreve(valore) {
    if (!valore) return '—';
    const d = new Date(valore);
    return Number.isNaN(d.getTime()) ? String(valore) : data.format(d);
}

function mostra(...nodi) {
    const contenitore = document.getElementById('contenuto');
    contenitore.replaceChildren(...nodi);
    window.scrollTo({ top: 0 });
}

function errore(descrizione) {
    return el('div', { classe: 'messaggio errore', testo: descrizione });
}

/* --- client API ------------------------------------------------------------------ */

async function api(percorso, { conToken = true, ...opzioni } = {}) {
    const intestazioni = { Accept: 'application/json', ...(opzioni.headers || {}) };

    if (opzioni.body !== undefined) {
        intestazioni['Content-Type'] = 'application/json';
    }

    const autenticata = conToken && Boolean(stato.token);

    if (autenticata) {
        intestazioni.Authorization = `Bearer ${stato.token}`;
    }

    let risposta;

    try {
        risposta = await fetch(API + percorso, { ...opzioni, headers: intestazioni });
    } catch {
        throw new Error('Impossibile contattare il servizio: controlla la connessione e riprova.');
    }

    // Un 401 significa "sessione scaduta" solo se avevamo davvero mandato un token: sulla
    // richiesta di accesso lo stesso codice vuol dire "credenziali sbagliate", ed è un errore
    // di chi sta scrivendo, non della sessione.
    if (risposta.status === 401 && autenticata) {
        esciLocalmente();
        avviso = 'Sessione scaduta: accedi di nuovo.';
        location.hash = '#/accedi';

        const scaduta = new Error(avviso);
        scaduta.sessioneScaduta = true;
        throw scaduta;
    }

    if (risposta.status === 429) {
        throw new Error('Troppi tentativi ravvicinati: attendi un minuto e riprova.');
    }

    const corpo = await risposta.json().catch(() => ({}));

    if (!risposta.ok) {
        throw new Error(corpo.errore || `Errore ${risposta.status}`);
    }

    return corpo;
}

function entra(token, utente) {
    stato.token = token;
    stato.utente = utente;
    localStorage.setItem(CHIAVI.token, token);
    localStorage.setItem(CHIAVI.utente, JSON.stringify(utente));
    disegnaNavigazione();
}

function esciLocalmente() {
    stato.token = null;
    stato.utente = null;
    localStorage.removeItem(CHIAVI.token);
    localStorage.removeItem(CHIAVI.utente);
    disegnaNavigazione();
}

async function esci() {
    try {
        await api('/api/logout', { method: 'POST' });
    } catch {
        // Il token potrebbe essere già scaduto: l'uscita locale vale comunque.
    }

    esciLocalmente();
    location.hash = '#/';
}

/* --- intestazione ---------------------------------------------------------------- */

function disegnaNavigazione() {
    let contenitore = document.getElementById('voce-accesso');

    if (!contenitore) {
        contenitore = el('span', { id: 'voce-accesso' });
        document.querySelector('.navigazione').append(contenitore);
    }

    if (stato.utente) {
        contenitore.replaceChildren(
            el('a', { href: '#/area', classe: 'navigazione-utente', testo: `${stato.utente.nome} ${stato.utente.cognome}` }),
            ' · ',
            ...(['funzionario', 'amministratore'].includes(stato.utente.ruolo)
                ? [el('a', { href: '#/sportello', testo: 'sportello' }), ' · ']
                : []),
            el('button', { classe: 'azione-uscita', testo: 'esci', onclick: esci }),
        );
        return;
    }

    contenitore.replaceChildren(
        el('a', { classe: 'accesso', href: '#/accedi', testo: 'Area riservata' }),
    );
}

/* --- viste pubbliche -------------------------------------------------------------- */

function vistaCasa() {
    const servizi = [
        ['Posizione fiscale', 'Consulta le cartelle, gli importi dovuti e le scadenze.', '#/cartelle'],
        ['Pratiche', 'Verifica lo stato delle richieste presentate agli uffici.', '#/pratiche'],
        ['Rimborsi', 'Consulta gli esiti delle liquidazioni e gli accrediti.', '#/rimborsi'],
    ];

    mostra(
        el('h1', { testo: 'Sportello telematico' }),
        el('p', { classe: 'sottotitolo', testo: 'Servizi fiscali online per i cittadini.' }),
        el('div', { classe: 'sportello-home' },
            el('section', {},
                el('h2', { testo: 'Servizi al cittadino' }),
                el('ul', { classe: 'elenco-servizi' },
                    ...servizi.map(([titolo, descrizione, href]) =>
                        el('li', {},
                            el('a', { href, testo: titolo }),
                            el('p', { testo: descrizione }))))),
            el('aside', { classe: 'accesso-riservato' },
                el('h2', { testo: 'Area riservata' }),
                el('p', { testo: stato.utente
                    ? 'Torna alla tua area personale.'
                    : 'Per accedere usa il codice fiscale e la password della scheda account.' }),
                el('a', { classe: 'pulsante', href: stato.utente ? '#/area' : '#/accedi',
                    testo: stato.utente ? 'Vai alla tua area' : 'Accedi all’area riservata' }))),
    );
}

function vistaServizi() {
    // Le tessere che portano da qualche parte sono collegamenti veri: prima erano `div` con
    // l'etichetta «accesso da area riservata» e nessun modo di arrivarci.
    const disponibili = [
        ['Posizione fiscale', 'Cartelle, importi e scadenze.', '#/cartelle'],
        ['Pratiche', 'Richieste in corso e loro stato.', '#/pratiche'],
        ['Rimborsi', 'Liquidazioni e accrediti.', '#/rimborsi'],
        ['Assistenza', 'Contatti e segnalazioni.', '/assistenza', 'avviso di servizio'],
    ];

    const inArrivo = [
        ['Dichiarazioni', 'Modelli presentati negli ultimi anni.'],
        ['Appuntamenti', 'Prenotazione allo sportello territoriale.'],
    ];

    mostra(
        el('h1', { testo: 'Servizi' }),
        el('p', { classe: 'sottotitolo', testo: 'Elenco dei servizi disponibili al cittadino.' }),
        el('div', { classe: 'griglia' },
            ...disponibili.map(([titolo, descrizione, href, disponibilita]) =>
                el('a', { classe: 'tessera', href },
                    el('h3', { testo: titolo }),
                    el('p', { testo: descrizione }),
                    el('span', {
                        classe: `etichetta ${disponibilita ? 'in_sospeso' : 'approvato'}`,
                        testo: disponibilita ?? 'disponibile',
                    }))),
            ...inArrivo.map(([titolo, descrizione]) =>
                el('div', { classe: 'tessera' },
                    el('h3', { testo: titolo }),
                    el('p', { testo: descrizione }),
                    el('span', { classe: 'etichetta in_lavorazione', testo: 'non ancora disponibile' }))),
        ),
    );
}

function vistaComeFare() {
    mostra(
        el('h1', { testo: 'Come fare per' }),
        el('div', { classe: 'pannello' },
            el('h2', { testo: 'Accedere all\'area riservata' }),
            el('p', { testo: 'Serve il codice fiscale e la password consegnata allo sportello. La password può essere cambiata dal profilo.' }),
            el('h2', { testo: 'Consultare una cartella' }),
            el('p', { testo: 'Dopo l\'accesso, la sezione «La mia posizione» elenca le cartelle emesse a proprio carico, con importo e scadenza.' }),
            el('h2', { testo: 'Chiedere un rimborso' }),
            el('p', { testo: 'Il rimborso si richiede dalla pratica di liquidazione. L\'accredito avviene sul conto indicato.' }),
        ),
    );
}


/**
 * Registrazione del cittadino. La pagina esiste perché il portale vero l'ha avuta dal 2021; il
 * ruolo però lo decide il server, non il corpo della richiesta.
 */
function vistaRegistrati() {
    const campi = {
        nome: el('input', { id: 'nome', autocomplete: 'given-name', maxlength: '60', required: 'required' }),
        cognome: el('input', { id: 'cognome', autocomplete: 'family-name', maxlength: '60', required: 'required' }),
        cf: el('input', { id: 'cf', name: 'codice_fiscale', maxlength: '16', required: 'required' }),
        email: el('input', { id: 'email', type: 'email', autocomplete: 'email', required: 'required' }),
        nascita: el('input', { id: 'nascita', type: 'date', required: 'required' }),
        sesso: el('select', { id: 'sesso' },
            el('option', { value: 'M', testo: 'Maschile' }),
            el('option', { value: 'F', testo: 'Femminile' })),
        comune: el('input', { id: 'comune', placeholder: 'Roma', required: 'required' }),
        indirizzo: el('input', { id: 'indirizzo', autocomplete: 'street-address', maxlength: '120', required: 'required' }),
        cap: el('input', { id: 'cap', inputmode: 'numeric', maxlength: '5', placeholder: '00165', required: 'required' }),
        provincia: el('input', { id: 'provincia', maxlength: '2', placeholder: 'RM', required: 'required' }),
        pw: el('input', { id: 'pw', type: 'password', autocomplete: 'new-password', minlength: '8', required: 'required' }),
    };

    const riquadro = el('div');

    const form = el('form', { classe: 'modulo' },
        el('div', { classe: 'campo' }, el('label', { for: 'nome', testo: 'Nome' }), campi.nome),
        el('div', { classe: 'campo' }, el('label', { for: 'cognome', testo: 'Cognome' }), campi.cognome),
        el('div', { classe: 'campo' },
            el('label', { for: 'cf', testo: 'Codice fiscale' }),
            campi.cf,
            el('div', { classe: 'aiuto', testo: 'Sedici caratteri, come sulla tessera sanitaria.' })),
        el('div', { classe: 'campo' }, el('label', { for: 'email', testo: 'Email' }), campi.email),
        el('div', { classe: 'campo' }, el('label', { for: 'nascita', testo: 'Data di nascita' }), campi.nascita),
        el('div', { classe: 'campo' }, el('label', { for: 'sesso', testo: 'Sesso' }), campi.sesso),
        el('div', { classe: 'campo' }, el('label', { for: 'comune', testo: 'Comune di nascita' }), campi.comune),
        el('div', { classe: 'campo' }, el('label', { for: 'indirizzo', testo: 'Indirizzo di residenza' }), campi.indirizzo),
        el('div', { classe: 'campo' }, el('label', { for: 'cap', testo: 'CAP' }), campi.cap),
        el('div', { classe: 'campo' }, el('label', { for: 'provincia', testo: 'Provincia' }), campi.provincia),
        el('div', { classe: 'campo' },
            el('label', { for: 'pw', testo: 'Password' }),
            campi.pw,
            el('div', { classe: 'aiuto', testo: 'Almeno otto caratteri.' })),
        el('button', { type: 'submit', testo: 'Registrati' }),
        riquadro,
    );

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        riquadro.replaceChildren();

        try {
            const esito = await api('/api/registrazione', {
                conToken: false,
                method: 'POST',
                body: JSON.stringify({
                    nome: campi.nome.value.trim(),
                    cognome: campi.cognome.value.trim(),
                    codice_fiscale: campi.cf.value.trim().toUpperCase(),
                    email: campi.email.value.trim(),
                    data_nascita: campi.nascita.value,
                    sesso: campi.sesso.value,
                    comune_nascita: campi.comune.value.trim(),
                    indirizzo: campi.indirizzo.value.trim(),
                    cap: campi.cap.value.trim(),
                    provincia: campi.provincia.value.trim().toUpperCase(),
                    password: campi.pw.value,
                }),
            });

            // La registrazione non emette un token: si passa dal login, come farebbe una persona.
            const accesso = await api('/api/login', {
                conToken: false,
                method: 'POST',
                body: JSON.stringify({
                    codice_fiscale: campi.cf.value.trim().toUpperCase(),
                    password: campi.pw.value,
                }),
            });

            entra(accesso.token, accesso.utente);
            location.hash = '#/area';
        } catch (e) {
            riquadro.replaceChildren(errore(e.message));
        }
    });

    mostra(
        el('h1', { testo: 'Registrazione' }),
        el('p', { classe: 'sottotitolo', testo: 'Serve per chi non ha ricevuto le credenziali allo sportello.' }),
        el('div', { classe: 'pannello' }, form),
        el('p', { classe: 'sottotitolo' },
            el('a', { href: '#/accedi', testo: 'Hai già un account? Accedi.' })),
    );
}

function vistaAccedi() {
    const campoCf = el('input', { id: 'cf', name: 'codice_fiscale', autocomplete: 'username', maxlength: '16', required: 'required' });
    const campoPw = el('input', { id: 'pw', name: 'password', type: 'password', autocomplete: 'current-password', required: 'required' });
    const riquadro = el('div');

    const form = el('form', { classe: 'modulo' },
        el('div', { classe: 'campo' },
            el('label', { for: 'cf', testo: 'Codice fiscale' }),
            campoCf,
            el('div', { classe: 'aiuto', testo: 'Sedici caratteri, come sulla tessera sanitaria.' })),
        el('div', { classe: 'campo' },
            el('label', { for: 'pw', testo: 'Password' }),
            campoPw),
        el('button', { type: 'submit', testo: 'Accedi' }),
        riquadro,
    );

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        riquadro.replaceChildren();

        try {
            const accesso = await api('/api/login', {
                conToken: false,
                method: 'POST',
                body: JSON.stringify({
                    codice_fiscale: campoCf.value.trim().toUpperCase(),
                    password: campoPw.value,
                }),
            });

            entra(accesso.token, accesso.utente);
            location.hash = '#/area';
        } catch (e) {
            riquadro.replaceChildren(errore(e.message));
        }
    });

    // DIFETTO INTENZIONALE (L16, capitolo 4): il messaggio di ritorno viaggia nell'indirizzo e
    // viene scritto nella pagina come HTML. `?torna=<img src=x onerror=...>` esegue.
    const torna = new URLSearchParams(location.search).get('torna');
    if (torna) {
        const striscia = el('div', { classe: 'striscia-informativa' });
        striscia.innerHTML = `<strong>Bentornato.</strong> ${torna}`;
        document.getElementById('contenuto').prepend(striscia);
    }

    if (avviso) {
        riquadro.replaceChildren(errore(avviso));
        avviso = null;
    }

    mostra(
        el('h1', { testo: 'Area riservata' }),
        el('p', { classe: 'sottotitolo', testo: 'Inserisci le credenziali ricevute allo sportello.' }),
        el('div', { classe: 'pannello' }, form),
        el('p', { classe: 'sottotitolo' },
            el('a', { href: '#/registrati', testo: 'Non hai le credenziali? Registrati.' })),
    );
}

/* --- area riservata ---------------------------------------------------------------- */

async function vistaArea() {
    const dati = await api('/api/me');

    mostra(
        el('h1', { testo: `Buongiorno, ${dati.utente.nome}` }),
        el('p', { classe: 'sottotitolo', testo: `Codice fiscale ${dati.utente.codice_fiscale} · ruolo ${dati.utente.ruolo}` }),

        el('div', { classe: 'griglia' },
            el('div', { classe: 'tessera' },
                el('div', { classe: 'numero', testo: String(dati.conteggi.cartelle) }),
                el('h3', { testo: 'Cartelle' }),
                el('a', { href: '#/cartelle', testo: 'Vedi le cartelle' })),
            el('div', { classe: 'tessera' },
                el('div', { classe: 'numero', testo: String(dati.conteggi.pratiche) }),
                el('h3', { testo: 'Pratiche' }),
                el('a', { href: '#/pratiche', testo: 'Vedi le pratiche' })),
            el('div', { classe: 'tessera' },
                el('div', { classe: 'numero', testo: String(dati.conteggi.rimborsi) }),
                el('h3', { testo: 'Rimborsi' }),
                el('a', { href: '#/rimborsi', testo: 'Vedi i rimborsi' })),
        ),
    );
}

async function vistaCartelle() {
    const dati = await api('/api/cartelle');

    if (dati.elementi.length === 0) {
        mostra(el('h1', { testo: 'Cartelle' }), el('p', { testo: 'Nessuna cartella a tuo carico.' }));
        return;
    }

    const corpi = dati.elementi.map((c) =>
        el('tr', {},
            el('td', {}, el('a', { href: `#/cartella/${c.id}`, testo: c.protocollo })),
            el('td', { testo: String(c.anno) }),
            el('td', { classe: 'numero', testo: importo(c.importo) }),
            el('td', {}, etichetta(c.stato)),
            el('td', { testo: dataBreve(c.scadenza) }),
        ),
    );

    mostra(
        el('h1', { testo: 'Cartelle' }),
        el('p', { classe: 'sottotitolo', testo: `${dati.elementi.length} cartelle a tuo carico.` }),
        el('div', { classe: 'pannello' },
            el('table', {},
                el('thead', {}, el('tr', {},
                    el('th', { testo: 'Protocollo' }),
                    el('th', { testo: 'Anno' }),
                    el('th', { classe: 'numero', testo: 'Importo' }),
                    el('th', { testo: 'Stato' }),
                    el('th', { testo: 'Scadenza' }))),
                el('tbody', {}, ...corpi))),
    );
}

async function vistaCartella(id) {
    const dati = await api(`/api/cartelle/${id}`);
    const c = dati.cartella;

    mostra(
        el('h1', { testo: `Cartella ${c.protocollo}` }),
        el('div', { classe: 'pannello' },
            el('dl', { classe: 'righe-dettaglio' },
                el('dt', { testo: 'Importo' }), el('dd', { testo: importo(c.importo) }),
                el('dt', { testo: 'Anno' }), el('dd', { testo: String(c.anno) }),
                el('dt', { testo: 'Stato' }), el('dd', {}, etichetta(c.stato)),
                el('dt', { testo: 'Scadenza' }), el('dd', { testo: dataBreve(c.scadenza) }),
                el('dt', { testo: 'Codice fiscale' }), el('dd', { testo: c.codice_fiscale }))),
        el('a', { classe: 'pulsante secondario', href: '#/cartelle', testo: 'Torna alle cartelle' }),
    );
}

async function vistaPratiche() {
    const dati = await api('/api/pratiche');

    const corpi = dati.elementi.map((p) =>
        el('tr', {},
            el('td', {}, el('a', { href: `#/pratica/${p.id}`, testo: p.protocollo })),
            el('td', { testo: p.servizio }),
            el('td', {}, etichetta(p.stato)),
            el('td', { testo: dataBreve(p.aperta_il) }),
        ),
    );

    mostra(
        el('h1', { testo: 'Pratiche' }),
        el('p', { classe: 'sottotitolo', testo: `${dati.elementi.length} pratiche presentate.` }),
        el('div', { classe: 'pannello' },
            el('table', {},
                el('thead', {}, el('tr', {},
                    el('th', { testo: 'Protocollo' }),
                    el('th', { testo: 'Servizio' }),
                    el('th', { testo: 'Stato' }),
                    el('th', { testo: 'Aperta il' }))),
                el('tbody', {}, ...corpi))),
    );
}

async function vistaPratica(id) {
    const dati = await api(`/api/pratiche/${id}`);
    const p = dati.pratica;

    const note = (p.note || []).map((n) =>
        el('div', { classe: 'note' },
            el('div', { classe: 'meta', testo: dataOra.format(new Date(n.creata_il)) }),
            // M1: il testo della nota è inserito come testo, non come HTML. In M4 diventerà
            // `innerHTML` sul modulo sportello: è il laboratorio L15.
            el('div', { classe: 'ellegale', testo: n.corpo })));

    const allegati = (p.allegati || []).map((a) =>
        el('li', {}, el('span', { testo: a.nome_file }), ` — ${Math.round(a.dimensione / 1024)} kB`));

    mostra(
        el('h1', { testo: `Pratica ${p.protocollo}` }),
        el('div', { classe: 'pannello' },
            el('dl', { classe: 'righe-dettaglio' },
                el('dt', { testo: 'Servizio' }), el('dd', { testo: p.servizio }),
                el('dt', { testo: 'Stato' }), el('dd', {}, etichetta(p.stato)),
                el('dt', { testo: 'Aperta il' }), el('dd', { testo: dataBreve(p.aperta_il) }))),
        el('h2', { testo: 'Comunicazioni' }),
        note.length ? el('div', { classe: 'pannello' }, ...note) : el('p', { testo: 'Nessuna comunicazione.' }),
        el('h2', { testo: 'Allegati' }),
        allegati.length ? el('div', { classe: 'pannello' }, el('ul', {}, ...allegati)) : el('p', { testo: 'Nessun allegato.' }),
        el('a', { classe: 'pulsante secondario', href: '#/pratiche', testo: 'Torna alle pratiche' }),
    );
}

async function vistaRimborsi() {
    const dati = await api('/api/rimborsi');

    const corpi = dati.elementi.map((r) =>
        el('tr', {},
            el('td', { testo: String(r.id) }),
            el('td', { classe: 'numero', testo: importo(r.importo) }),
            el('td', {}, etichetta(r.stato)),
            el('td', { testo: dataBreve(r.richiesto_il) }),
            el('td', { testo: r.iban || '—' }),
        ),
    );

    mostra(
        el('h1', { testo: 'Rimborsi' }),
        el('p', { classe: 'sottotitolo', testo: `${dati.elementi.length} rimborsi registrati.` }),
        el('div', { classe: 'pannello' },
            el('table', {},
                el('thead', {}, el('tr', {},
                    el('th', { testo: 'N.' }),
                    el('th', { classe: 'numero', testo: 'Importo' }),
                    el('th', { testo: 'Stato' }),
                    el('th', { testo: 'Richiesto il' }),
                    el('th', { testo: 'IBAN' }))),
                el('tbody', {}, ...corpi))),
    );
}

/* --- instradamento ---------------------------------------------------------------- */


/**
 * Schermata del funzionario. Le note dei cittadini arrivano dall'API come testo e vengono
 * inserite con `innerHTML`: DIFETTO INTENZIONALE (L15, capitolo 4). Il codice che le rende è il
 * punto in cui una nota scritta male diventa codice eseguito sul browser del funzionario.
 */
async function vistaSportello() {
    const dati = await api('/api/sportello/pratiche');

    const righe = dati.elementi.map((p) => {
        const cellaNote = el('td');
        (p.note || []).forEach((n) => {
            const nota = el('div', { classe: 'nota-cittadino' });
            nota.innerHTML = n.corpo;
            cellaNote.append(nota);
        });

        return el('tr', {},
            el('td', { testo: p.protocollo }),
            el('td', { testo: p.servizio }),
            el('td', { testo: p.codice_fiscale }),
            cellaNote);
    });

    mostra(
        el('h1', { testo: 'Sportello' }),
        el('p', { classe: 'sottotitolo', testo: 'Pratiche assegnate al suo ufficio, con le note dei contribuenti.' }),
        el('div', { classe: 'pannello' },
            el('table', {},
                el('thead', {}, el('tr', {},
                    el('th', { testo: 'Protocollo' }),
                    el('th', { testo: 'Servizio' }),
                    el('th', { testo: 'Contribuente' }),
                    el('th', { testo: 'Note' }))),
                el('tbody', {}, ...righe))),
    );
}


/**
 * Epilogo: la scala dei privilegi e la lista dei rimedi, in una pagina sola.
 * Non è un difetto: è il riassunto che serve al docente per chiudere il corso.
 */
function vistaEpilogo() {
    const passi = [
        ['anonimo', 'Legge i protocolli pubblici, il registro, il `.env` via allegati.'],
        ['cittadino', 'Entra con una password del dizionario o con `\' OR 1=1#`; poi legge i fascicoli altrui.'],
        ['altro cittadino', 'Si prende un fascicolo con l\'IDOR o cambia il proprio codice fiscale.'],
        ['funzionario', 'Conquista il browser di chi lavora con una nota, e il token non scade.'],
        ['amministratore', 'Il ruolo è un campo modificabile, e `admin`/`admin` vale sull\'anagrafe interna.'],
    ];

    const rimedi = [
        'Query parametrizzate, ovunque, senza eccezioni.',
        'Autorizzazione sull\'oggetto, non sulla rotta.',
        'Nessun `innerHTML` su contenuto esterno, più una CSP.',
        'Token con scadenza e revoca; segreti fuori dal bundle.',
        'Transazioni e vincoli per i soldi; niente `shell_exec`.',
        'Liste di domini ammessi per le richieste in uscita.',
        'Log degli accessi falliti, con orario e fuso dichiarati.',
    ];

    mostra(
        el('h1', { testo: 'Epilogo' }),
        el('p', { classe: 'sottotitolo', testo: 'Dove si arriva, partendo da un modulo di contatti.' }),
        el('div', { classe: 'pannello' },
            el('table', {},
                el('thead', {}, el('tr', {}, el('th', { testo: 'Livello' }), el('th', { testo: 'Come ci si arriva' }))),
                el('tbody', {}, ...passi.map(([livello, come]) =>
                    el('tr', {}, el('td', { testo: livello }), el('td', { testo: come })))))),
        el('h2', { testo: 'I rimedi, uno per capitolo' }),
        el('ul', {}, ...rimedi.map((r) => el('li', { testo: r }))),
        el('p', { classe: 'sottotitolo', testo: 'Nessuno di questi difetti richiede un attaccante esperto: richiede che nessuno abbia guardato.' }),
    );
}

const rotte = [
    [/^#\/$/, vistaCasa],
    [/^#\/servizi$/, vistaServizi],
    [/^#\/come-fare$/, vistaComeFare],
    [/^#\/accedi$/, vistaAccedi],
    [/^#\/registrati$/, vistaRegistrati],
    [/^#\/area$/, vistaArea, true],
    [/^#\/cartelle$/, vistaCartelle, true],
    [/^#\/cartella\/(\d+)$/, vistaCartella, true],
    [/^#\/pratiche$/, vistaPratiche, true],
    [/^#\/pratica\/(\d+)$/, vistaPratica, true],
    [/^#\/rimborsi$/, vistaRimborsi, true],
    [/^#\/sportello$/, vistaSportello, true],
    [/^#\/epilogo$/, vistaEpilogo],
];

async function instrada() {
    const percorso = location.hash || '#/';
    const trovata = rotte.find(([espressione]) => espressione.test(percorso));

    if (!trovata) {
        mostra(el('h1', { testo: 'Pagina non trovata' }), el('a', { href: '#/', testo: 'Torna alla home' }));
        return;
    }

    const [, vista, riservata] = trovata;

    if (riservata && !stato.token) {
        // Chi arriva qui da una tessera della pagina Servizi merita una riga che spieghi perché
        // si trova davanti al modulo di accesso.
        avviso = 'Per usare questo servizio serve l\'accesso all\'area riservata.';
        location.hash = '#/accedi';
        return;
    }

    try {
        const argomenti = percorso.match(trovata[0]).slice(1).map((v) => (v === undefined ? v : decodeURIComponent(v)));
        await vista(...argomenti.map((v) => (/^\d+$/.test(v) ? Number(v) : v)));
    } catch (e) {
        // La sessione scaduta ha già portato alla pagina di accesso con il proprio avviso.
        if (e.sessioneScaduta) return;

        mostra(el('h1', { testo: 'Si è verificato un problema' }), errore(e.message));
    }
}

disegnaNavigazione();
window.addEventListener('hashchange', instrada);
instrada();
