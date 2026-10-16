<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préférences de notification d'un utilisateur, par canal et par sujet. Une instance sans ligne
 * en base porte les valeurs par défaut (voir la migration). L'activation des SMS est portée par
 * `users.sms_alerts_enabled`, le numéro par `users.phone`.
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email_daily_report', 'email_alerts', 'email_stock', 'email_vaccinations', 'email_weather', 'email_payments',
        'app_enabled', 'app_alerts', 'app_stock', 'app_weather', 'app_payments',
        'sms_critical_alerts', 'sms_payments',
    ];

    /** @var array<string, bool> */
    protected $attributes = [
        'email_daily_report' => true,
        'email_alerts' => true,
        'email_stock' => true,
        'email_vaccinations' => true,
        'email_weather' => true,
        'email_payments' => true,
        'app_enabled' => true,
        'app_alerts' => true,
        'app_stock' => true,
        'app_weather' => true,
        'app_payments' => false,
        'sms_critical_alerts' => true,
        'sms_payments' => false,
    ];

    /** Champs du formulaire, dans l'ordre de la page. */
    public const TOGGLES = [
        'email_daily_report', 'email_alerts', 'email_stock', 'email_vaccinations', 'email_weather', 'email_payments',
        'app_enabled', 'app_alerts', 'app_stock', 'app_weather', 'app_payments',
        'sms_critical_alerts', 'sms_payments',
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::TOGGLES, 'boolean');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wantsEmail(?NotificationCategory $category): bool
    {
        return match ($category) {
            null => true,
            NotificationCategory::DailyReport => (bool) $this->email_daily_report,
            NotificationCategory::Alerts => (bool) $this->email_alerts,
            NotificationCategory::Stock => (bool) $this->email_stock,
            NotificationCategory::Vaccinations => (bool) $this->email_vaccinations,
            NotificationCategory::Weather => (bool) $this->email_weather,
            NotificationCategory::Payments => (bool) $this->email_payments,
        };
    }

    /**
     * Cloche du panel et onglet Alertes du mobile. Les rappels de vaccination suivent les alertes
     * générales ; le rapport quotidien n'existe que par e-mail.
     */
    public function wantsInApp(?NotificationCategory $category): bool
    {
        if ($category === null) {
            return true;
        }

        if (! $this->app_enabled) {
            return false;
        }

        return match ($category) {
            NotificationCategory::DailyReport => false,
            NotificationCategory::Alerts, NotificationCategory::Vaccinations => (bool) $this->app_alerts,
            NotificationCategory::Stock => (bool) $this->app_stock,
            NotificationCategory::Weather => (bool) $this->app_weather,
            NotificationCategory::Payments => (bool) $this->app_payments,
        };
    }

    /**
     * SMS : seulement les alertes critiques (et les paiements si demandé) ; jamais un message de
     * service ni le rapport quotidien.
     */
    public function wantsSms(?NotificationCategory $category, bool $critical): bool
    {
        return match ($category) {
            null, NotificationCategory::DailyReport => false,
            NotificationCategory::Payments => (bool) $this->sms_payments,
            // Météo : seules les conditions critiques (canicule ou froid extrêmes) méritent un SMS
            default => $critical && (bool) $this->sms_critical_alerts,
        };
    }
}
