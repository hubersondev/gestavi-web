<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rapprochement des encaissements mobile money
    |--------------------------------------------------------------------------
    |
    | Chaque opérateur pousse ses notifications de paiement sur
    | POST /api/webhooks/mobile-money/{operator}. Le corps brut est signé en
    | HMAC-SHA256 avec le secret partagé configuré ici (en-tête X-Signature ou
    | X-Webhook-Signature, préfixe « sha256= » accepté). Sans secret configuré,
    | le webhook de l'opérateur est refusé.
    |
    */

    // Écart toléré (XOF) entre le montant reçu et le solde dû d'une facture pour un rapprochement
    'match_tolerance' => (float) env('MOBILE_MONEY_MATCH_TOLERANCE', 1),

    // Âge minimal (minutes) d'une transaction « reçue » avant nouvelle tentative de rapprochement planifiée
    'auto_match_min_age_minutes' => (int) env('MOBILE_MONEY_AUTO_MATCH_MIN_AGE', 10),

    'operators' => [
        'orange' => [
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
        ],
        'mtn' => [
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
        ],
        'moov' => [
            'webhook_secret' => env('MOOV_MONEY_WEBHOOK_SECRET'),
        ],
        'wave' => [
            'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        ],
    ],

];
