<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ancrage hors base de la chaîne de hachage
    |--------------------------------------------------------------------------
    |
    | À chaque ancrage (clôture d'exercice, reconstruction, planification), la
    | tête de chaîne est ajoutée à un fichier JSON Lines sur ce disque. Un disque
    | distant (S3, SFTP) rend l'ancrage indépendant de l'administrateur de la base.
    |
    */

    'anchor_disk' => env('LEDGER_ANCHOR_DISK', 'local'),

    'anchor_path' => env('LEDGER_ANCHOR_PATH', 'ledger'),

];
