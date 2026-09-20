<?php

namespace App\Services\Sms\Jobs;

use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoi asynchrone d'un SMS journalisé (sms_messages).
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly SmsMessage $message) {}

    public function handle(SmsManager $manager): void
    {
        if ($this->message->status !== SmsMessage::STATUS_QUEUED) {
            return;
        }

        $manager->sendAndLog($this->message);
    }
}
