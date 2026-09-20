<?php

use App\Models\Organisation;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\OrganisationAlertNotification;
use App\Services\Sms\Drivers\LogSmsSender;
use App\Services\Sms\Jobs\SendSmsJob;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Canal SMS : normalisation des numéros, pilote de journalisation, opt-in des destinataires,
 * journal sms_messages et envoi en file.
 */
it('normalise les numéros ivoiriens au format E.164', function (?string $raw, ?string $expected) {
    expect(PhoneNumber::normalize($raw))->toBe($expected);
})->with([
    'espaces' => ['07 07 07 07 07', '+2250707070707'],
    'compact' => ['0505050505', '+2250505050505'],
    'indicatif sans plus' => ['2250101010101', '+2250101010101'],
    'indicatif avec plus et espaces' => ['+225 07 07 07 07 07', '+2250707070707'],
    'double zéro international' => ['00225 0707070707', '+2250707070707'],
    'ancien format 8 chiffres refusé' => ['07070707', null],
    'trop court' => ['0707', null],
    'vide' => ['', null],
    'null' => [null, null],
]);

it('le pilote log journalise le message sans rien envoyer', function () {
    Log::shouldReceive('channel')->once()->with(null)->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === '[SMS] Envoi simulé' && $context['to'] === '+2250707070707');

    $result = (new LogSmsSender)->send('+2250707070707', 'Bonjour');

    expect($result->success)->toBeTrue()
        ->and($result->driver)->toBe('log')
        ->and($result->providerId)->toStartWith('log-');
});

it('le gestionnaire résout le pilote configuré et refuse un pilote inconnu', function () {
    config(['sms.default' => 'log']);

    expect(app(SmsManager::class)->driver())->toBeInstanceOf(LogSmsSender::class);

    config(['sms.default' => 'inexistant']);

    expect(fn () => app(SmsManager::class)->driver())->toThrow(InvalidArgumentException::class);
});

it('n\'ajoute le canal SMS qu\'aux destinataires opt-in avec un numéro valide', function () {
    Notification::fake();

    $organisation = Organisation::factory()->create();
    $optIn = User::factory()->create(['phone' => '07 07 07 07 07', 'sms_alerts_enabled' => true]);
    $optOut = User::factory()->create(['phone' => '0505050505', 'sms_alerts_enabled' => false]);
    $noPhone = User::factory()->create(['phone' => null, 'sms_alerts_enabled' => true]);

    $notification = new OrganisationAlertNotification(
        title: '3 vaccinations en retard',
        lines: ['• Gumboro — bande BND-2026-001'],
        alertKey: 'overdue_vaccinations',
        organisationId: $organisation->id,
    );

    Notification::send([$optIn, $optOut, $noPhone], $notification);

    Notification::assertSentTo($optIn, OrganisationAlertNotification::class, fn ($n, array $channels): bool => in_array(SmsChannel::class, $channels, true));
    Notification::assertSentTo($optOut, OrganisationAlertNotification::class, fn ($n, array $channels): bool => ! in_array(SmsChannel::class, $channels, true));
    Notification::assertSentTo($noPhone, OrganisationAlertNotification::class, fn ($n, array $channels): bool => ! in_array(SmsChannel::class, $channels, true));

    expect($notification->toSms($optIn))->toStartWith('GESTAVI - 3 vaccinations en retard : Gumboro')
        ->and(mb_strlen($notification->toSms($optIn)))->toBeLessThanOrEqual(160);
});

it('le canal journalise le SMS en attente puis le job l\'envoie via le pilote log', function () {
    Queue::fake();

    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['phone' => '0707070707', 'sms_alerts_enabled' => true]);

    $notification = new OrganisationAlertNotification(
        title: 'Stock bas',
        lines: ['• Aliment démarrage : 3 sacs restants'],
        alertKey: 'low_stock',
        organisationId: $organisation->id,
    );

    $message = (new SmsChannel)->send($user, $notification);

    expect($message)->toBeInstanceOf(SmsMessage::class)
        ->and($message->status)->toBe(SmsMessage::STATUS_QUEUED)
        ->and($message->to)->toBe('+2250707070707')
        ->and($message->organisation_id)->toBe($organisation->id)
        ->and($message->user_id)->toBe($user->id);

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->is($message));

    (new SendSmsJob($message))->handle(app(SmsManager::class));

    $message->refresh();

    expect($message->status)->toBe(SmsMessage::STATUS_SENT)
        ->and($message->driver)->toBe('log')
        ->and($message->provider_id)->toStartWith('log-')
        ->and($message->sent_at)->not->toBeNull();
});

it('le canal n\'envoie rien à un destinataire sans numéro exploitable', function () {
    Queue::fake();

    $user = User::factory()->create(['phone' => '1234', 'sms_alerts_enabled' => true]);

    $notification = new OrganisationAlertNotification(title: 'Test', lines: [], alertKey: 'test', organisationId: 1);

    expect((new SmsChannel)->send($user, $notification))->toBeNull()
        ->and(SmsMessage::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});
