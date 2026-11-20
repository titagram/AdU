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

    // Origini ammesse dalla SPA. In M5 diventerà '*'.
    'cors_origins' => explode(',', env('ADU_CORS_ORIGINS', 'http://127.0.0.1:8080')),
];
