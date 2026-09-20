<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pilote SMS par défaut
    |--------------------------------------------------------------------------
    |
    | `log` n'envoie rien et écrit le message dans le journal Laravel (défaut,
    | développement). `africastalking` et `orange` appellent les API des
    | opérateurs via le client HTTP Laravel ; aucun SDK externe n'est requis.
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Indicatif pays par défaut (Côte d'Ivoire) pour la normalisation E.164
    |--------------------------------------------------------------------------
    */

    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '225'),

    'drivers' => [

        'log' => [
            'channel' => env('SMS_LOG_CHANNEL'),
        ],

        // https://developers.africastalking.com/docs/sms/sending/bulk
        'africastalking' => [
            'username' => env('AT_USERNAME', 'sandbox'),
            'api_key' => env('AT_API_KEY'),
            'sender_id' => env('AT_SENDER_ID'),
            'base_url' => env('AT_BASE_URL', 'https://api.africastalking.com'),
        ],

        // Orange SMS API (Côte d'Ivoire) : OAuth2 client credentials puis /smsmessaging/v1
        'orange' => [
            'client_id' => env('ORANGE_SMS_CLIENT_ID'),
            'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
            'sender' => env('ORANGE_SMS_SENDER'),
            'sender_name' => env('ORANGE_SMS_SENDER_NAME', 'GESTAVI'),
            'base_url' => env('ORANGE_SMS_BASE_URL', 'https://api.orange.com'),
        ],
    ],

];
