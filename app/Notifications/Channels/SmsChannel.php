<?php

namespace App\Notifications\Channels;

use App\Services\Sms\Jobs\SendSmsJob;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsMessage;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification `sms` : la notification expose `toSms($notifiable): string`,
 * le destinataire expose `routeNotificationForSms()` (ou un attribut `phone`).
 * Le message est journalisé puis envoyé en file par SendSmsJob.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): ?SmsMessage
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $to = PhoneNumber::normalize($notifiable->routeNotificationFor('sms', $notification) ?? $notifiable->phone ?? null);

        if ($to === null) {
            return null;
        }

        $text = trim((string) $notification->toSms($notifiable));

        if ($text === '') {
            return null;
        }

        $message = SmsMessage::create([
            'organisation_id' => $notification->organisationId ?? null,
            'user_id' => $notifiable->getKey(),
            'to' => $to,
            'message' => mb_substr($text, 0, 320),
            'driver' => (string) config('sms.default', 'log'),
            'status' => SmsMessage::STATUS_QUEUED,
        ]);

        SendSmsJob::dispatch($message);

        return $message;
    }
}
