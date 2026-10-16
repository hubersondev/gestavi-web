<?php

namespace App\Enums;

/**
 * Sujets de notification que l'utilisateur peut activer ou couper, canal par canal.
 */
enum NotificationCategory: string
{
    case DailyReport = 'daily_report';
    case Alerts = 'alerts';
    case Stock = 'stock';
    case Vaccinations = 'vaccinations';
    case Payments = 'payments';
    case Weather = 'weather';

    /**
     * Sujet d'une alerte d'après sa clé ; null = message de service (export demandé…) qui passe
     * toujours par les canaux de base. Les factures échues et les encaissements non rapprochés
     * sont des alertes à traiter, pas des confirmations de paiement.
     */
    public static function fromAlertKey(string $alertKey): ?self
    {
        return match ($alertKey) {
            'daily_report' => self::DailyReport,
            'low_stock', 'expiring_stock_lots', 'expired_stock_lots' => self::Stock,
            'overdue_vaccinations', 'upcoming_vaccinations' => self::Vaccinations,
            'payment_received' => self::Payments,
            'weather_warning', 'weather_critical' => self::Weather,
            'report_export' => null,
            default => self::Alerts,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DailyReport => 'Rapport quotidien',
            self::Alerts => 'Alertes générales',
            self::Stock => 'Alertes stock',
            self::Vaccinations => 'Rappels vaccination',
            self::Payments => 'Paiements reçus',
            self::Weather => 'Alertes météo',
        };
    }
}
