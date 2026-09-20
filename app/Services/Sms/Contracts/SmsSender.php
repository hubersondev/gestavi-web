<?php

namespace App\Services\Sms\Contracts;

use App\Services\Sms\SmsResult;

interface SmsSender
{
    /**
     * Envoie un SMS à un numéro E.164 (+225…). Ne lève pas : le résultat porte l'erreur.
     */
    public function send(string $to, string $message): SmsResult;

    public function name(): string;
}
