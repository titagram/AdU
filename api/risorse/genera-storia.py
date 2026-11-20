#!/usr/bin/env python3
"""Costruisce il finto repository che il portale esporrà su /.git (laboratorio L19).

Il difetto raccontato dal corso è un `.git` finito nella DocumentRoot: chi lo legge ottiene il
sorgente del portale e, soprattutto, la storia dei commit. La storia qui dentro è costruita a
mano perché il colpo di scena del capitolo 1 sia vero e verificabile: un commit disattiva il
rate limit «per il collaudo», un altro lo riattiva mesi dopo.

Si rigenera con: python3 risorse/genera-storia.py
"""

import os
import shutil
import subprocess
from pathlib import Path

RADICE = Path(__file__).resolve().parent.parent
LAVORO = Path("/tmp/storia-portale")
DESTINAZIONE = RADICE / "risorse" / "reperti" / "git"

ESCLUDI = shutil.ignore_patterns(
    "vendor", "node_modules", "storage", "risorse", ".git", ".env",
    "cache", "*.log", "__pycache__",
)


def git(*argomenti):
    return subprocess.run(
        ["git", "-C", str(LAVORO), *argomenti],
        check=True, capture_output=True, text=True,
    ).stdout


def commit(quando, messaggio):
    a = f"{quando} 10:00:00 +0100"
    ambiente = os.environ | {"GIT_AUTHOR_DATE": a, "GIT_COMMITTER_DATE": a}
    subprocess.run(
        ["git", "-C", str(LAVORO), "add", "-A"], check=True, capture_output=True
    )
    subprocess.run(
        ["git", "-C", str(LAVORO), "commit", "-q", "-m", messaggio],
        check=True, capture_output=True, env=ambiente,
    )


LIMITATORE = """        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtoupper((string) $request->input('codice_fiscale')).'|'.$request->ip()
            );
        });"""


def limita(attivo: bool):
    """Accende o spegne il limitatore, come farebbe un `git revert` della protezione."""
    percorso = LAVORO / "app" / "Providers" / "AppServiceProvider.php"
    percorso.write_text(
        percorso.read_text().replace(
            LIMITATORE, LIMITATORE if attivo else "/*\n" + LIMITATORE + "\n        */"
        )
    )


def sostituisci(percorso, vecchio, nuovo):
    f = LAVORO / percorso
    testo = f.read_text()
    assert vecchio in testo, f"testo non trovato in {percorso}"
    f.write_text(testo.replace(vecchio, nuovo))


def main():
    if LAVORO.exists():
        shutil.rmtree(LAVORO)
    shutil.copytree(RADICE, LAVORO, ignore=ESCLUDI)

    git("init", "-q", "-b", "main")
    git("config", "user.name", "Servizio Sistemi Informativi")
    git("config", "user.email", "sistemi.informativi@agenzia-uscite.example")
    git("config", "core.autocrlf", "false")

    # Il primo commit è il portale senza protezione sui tentativi: è la versione che l'ente
    # ha messo in esercizio.
    sostituisci(
        "routes/api.php",
        "Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');",
        "Route::post('/login', [AuthController::class, 'login']);",
    )
    limita(False)

    commit("2024-11-04", "Prima versione del portale dei tributi")

    # Il rate limit arriva in primavera, dopo che qualcuno se ne accorge.
    sostituisci(
        "routes/api.php",
        "Route::post('/login', [AuthController::class, 'login']);",
        "Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');",
    )
    limita(True)
    commit("2025-03-21", "Aggiunge un limite ai tentativi di accesso")

    # Il commit che gli studenti devono trovare.
    sostituisci(
        "routes/api.php",
        "Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');",
        "// rate limit disattivato per il collaudo, riattivare prima della produzione\nRoute::post('/login', [AuthController::class, 'login']);",
    )
    commit("2025-04-02", "Collaudo di carico: disattiva temporaneamente il rate limit")

    # Cinque mesi e mezzo dopo.
    sostituisci(
        "routes/api.php",
        "// rate limit disattivato per il collaudo, riattivare prima della produzione\nRoute::post('/login', [AuthController::class, 'login']);",
        "Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');",
    )
    commit("2025-09-30", "Riattiva il rate limit sul login")

    sostituisci(
        "database/seeders/DatabaseSeeder.php",
        "PASSWORD_DIMOSTRATIVA = ",
        "// Password dell'account consegnato allo sportello.\n    PASSWORD_DIMOSTRATIVA = ",
    )
    commit("2025-10-12", "Aggiorna i dati dimostrativi per la formazione")

    if DESTINAZIONE.exists():
        shutil.rmtree(DESTINAZIONE)
    shutil.copytree(LAVORO / ".git", DESTINAZIONE)

    # Il repository deve sembrare quello interno dell'ente, non un repo nato per il corso.
    config = DESTINAZIONE / "config"
    config.write_text(
        "[core]\n"
        "\trepositoryformatversion = 0\n"
        "\tfilemode = true\n"
        "\tbare = false\n"
        "\tlogallrefupdates = true\n"
        "[remote \"origin\"]\n"
        "\turl = git@git.agenzia-uscite.example:sistemi/portale-tributi.git\n"
        "\tfetch = +refs/heads/*:refs/remotes/origin/*\n"
        "[branch \"main\"]\n"
        "\tremote = origin\n"
        "\tmerge = refs/heads/main\n"
    )

    print(git("log", "--pretty=%ad %s", "--date=short"))
    dimensione = sum(f.stat().st_size for f in DESTINAZIONE.rglob("*") if f.is_file())
    print(f"dimensione del finto repository: {dimensione // 1024} KB")


if __name__ == "__main__":
    main()
