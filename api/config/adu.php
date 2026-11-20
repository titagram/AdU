<?php

/**
 * Parametri applicativi del portale.
 *
 * Stanno qui e non sparsi nei controller perché M2 dovrà cambiarne alcuni di proposito:
 * tenere la configurazione in un punto solo rende leggibile il diff che introduce il difetto.
 */
return [
    // Firma dei token. In M4 diventerà un segreto debole e noto: per ora è robusto.
    'jwt_secret' => env('ADU_JWT_SECRET', 'cambia-questo-segreto-in-produzione'),
    'jwt_issuer' => env('ADU_JWT_ISSUER', 'agenzia-uscite'),
    'jwt_ttl' => (int) env('ADU_JWT_TTL', 60),

    // DIFETTO INTENZIONALE (L19): `*`. Serviva a far lavorare il frontend di un'altra ditta su
    // indirizzi che cambiavano ogni giorno; è rimasto perché nessuno ha aperto un ticket.
    'portale_url' => env('ADU_PORTALE_URL', 'http://127.0.0.1:8080'),

    'cors_origins' => explode(',', env('ADU_CORS_ORIGINS', '*')),
];
