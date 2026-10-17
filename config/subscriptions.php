<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Délai de grâce après expiration
    |--------------------------------------------------------------------------
    |
    | Jours pendant lesquels une organisation dont l'abonnement est expiré garde son plan et la
    | saisie. Passé ce délai, sans renouvellement ni nouvel abonnement, elle passe en lecture
    | seule (consultation sans écriture) jusqu'à régularisation ; la réactivation est automatique
    | à la souscription ou au renouvellement.
    |
    */

    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

];
