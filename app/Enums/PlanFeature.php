<?php

namespace App\Enums;

enum PlanFeature: string
{
    // 1. Fermes & Infrastructure
    case MultiFarms = 'multi_farms';
    case Buildings = 'buildings';
    case Suppliers = 'suppliers';
    case FarmSettings = 'farm_settings';

    // 2. Production & Cycles Avicoles
    case Flocks = 'flocks';
    case DailyRecords = 'daily_records';
    case ProductionPhases = 'production_phases';
    case ZootechnicalCalculations = 'zootechnical_calculations';
    case ProductionAlerts = 'production_alerts';

    // 3. Santé & Vaccinations
    case HealthPrograms = 'health_programs';
    case Vaccinations = 'vaccinations';
    case VetVisits = 'vet_visits';
    case HealthIncidents = 'health_incidents';
    case FarmJournal = 'farm_journal';

    // 4. Gestion des Stocks
    case StockManagement = 'stock_management';
    case StockBatches = 'stock_batches';
    case PurchaseOrders = 'purchase_orders';
    case Inventories = 'inventories';
    case StockAlerts = 'stock_alerts';

    // 5. Ventes & Clients
    case Customers = 'customers';
    case Invoices = 'invoices';
    case Quotes = 'quotes';
    case CreditNotes = 'credit_notes';
    case Payments = 'payments';
    case Deliveries = 'deliveries';
    case SalesAlerts = 'sales_alerts';
    case SalesDashboard = 'sales_dashboard';

    // 6. Comptabilité & Finances
    case Accounting = 'accounting';
    case Transfers = 'transfers';
    case BankReconciliation = 'bank_reconciliation';
    case FinancialAudit = 'financial_audit';
    case FinanceDashboard = 'finance_dashboard';
    case Expenses = 'expenses';

    // 7. Équipements & Matériel
    case Equipment = 'equipment';
    case EquipmentMaintenance = 'equipment_maintenance';
    case Depreciation = 'depreciation';

    // 8. Ressources Humaines
    case Employees = 'employees';
    case Contracts = 'contracts';
    case LeaveManagement = 'leave_management';
    case Attendance = 'attendance';
    case HrDashboard = 'hr_dashboard';

    // 9. Paie & CNPS
    case Payroll = 'payroll';
    case CnpsCalculation = 'cnps_calculation';
    case PayrollDashboard = 'payroll_dashboard';
    case PayslipPdf = 'payslip_pdf';

    // 10. Contrôle de Présence & Sécurité
    case ExitAuthorizations = 'exit_authorizations';
    case SecurityPatrols = 'security_patrols';
    case PresenceAlerts = 'presence_alerts';

    // 11. Géofencing & Tracking GPS
    case Geofencing = 'geofencing';
    case GpsTracking = 'gps_tracking';
    case GeofenceAlerts = 'geofence_alerts';

    // 12. Système IoT
    case IotSensors = 'iot_sensors';
    case IotDashboard = 'iot_dashboard';

    // 13. Prédictions & IA
    case AiPredictions = 'ai_predictions';

    // 14. Météo
    case WeatherIntegration = 'weather_integration';

    // 15. Comparaison Multi-Fermes
    case MultiFarmComparison = 'multi_farm_comparison';

    // 16. Dashboard & Rapports
    case MainDashboard = 'main_dashboard';
    case Reports = 'reports';
    case ExcelExports = 'excel_exports';
    case PdfTemplates = 'pdf_templates';

    // 17. Alertes & Notifications
    case Notifications = 'notifications';
    case EmailNotifications = 'email_notifications';
    case SmsNotifications = 'sms_notifications';
    case DailyReport = 'daily_report';

    // 18. API Mobile
    case ApiAccess = 'api_access';

    // 19. Gestion Utilisateurs & Abonnements
    case TeamManagement = 'team_management';
    case Invitations = 'invitations';

    // 20. Tâches planifiées
    case Scheduler = 'scheduler';

    public function label(): string
    {
        return match ($this) {
            // 1. Fermes & Infrastructure
            self::MultiFarms => 'Gestion multi-fermes',
            self::Buildings => 'Gestion des bâtiments',
            self::Suppliers => 'Gestion des fournisseurs',
            self::FarmSettings => 'Paramétrage par ferme',

            // 2. Production & Cycles
            self::Flocks => 'Bandes de volailles',
            self::DailyRecords => 'Suivi quotidien',
            self::ProductionPhases => 'Phases de production',
            self::ZootechnicalCalculations => 'Calculs zootechniques (GMQ, IC)',
            self::ProductionAlerts => 'Alertes production',

            // 3. Santé & Vaccinations
            self::HealthPrograms => 'Programmes sanitaires',
            self::Vaccinations => 'Vaccinations & rappels',
            self::VetVisits => 'Visites vétérinaires',
            self::HealthIncidents => 'Incidents sanitaires',
            self::FarmJournal => 'Journal de ferme',

            // 4. Stocks
            self::StockManagement => 'Gestion des stocks',
            self::StockBatches => 'Lots de stock (FIFO, péremption)',
            self::PurchaseOrders => 'Bons de commande',
            self::Inventories => 'Inventaires',
            self::StockAlerts => 'Alertes stock',

            // 5. Ventes & Clients
            self::Customers => 'Gestion clients',
            self::Invoices => 'Facturation',
            self::Quotes => 'Devis',
            self::CreditNotes => 'Avoirs',
            self::Payments => 'Paiements multi-méthodes',
            self::Deliveries => 'Suivi des livraisons',
            self::SalesAlerts => 'Alertes ventes',
            self::SalesDashboard => 'Dashboard CA',

            // 6. Comptabilité
            self::Accounting => 'Comptabilité & transactions',
            self::Transfers => 'Transferts entre comptes',
            self::BankReconciliation => 'Rapprochement bancaire',
            self::FinancialAudit => 'Audit financier',
            self::FinanceDashboard => 'Dashboard comptable',
            self::Expenses => 'Gestion des dépenses',

            // 7. Équipements
            self::Equipment => 'Gestion des équipements',
            self::EquipmentMaintenance => 'Maintenance préventive/corrective',
            self::Depreciation => 'Amortissement',

            // 8. RH
            self::Employees => 'Gestion des employés',
            self::Contracts => 'Contrats de travail',
            self::LeaveManagement => 'Gestion des congés',
            self::Attendance => 'Pointage',
            self::HrDashboard => 'Dashboard RH',

            // 9. Paie
            self::Payroll => 'Bulletins de paie',
            self::CnpsCalculation => 'Calcul CNPS & ITS',
            self::PayrollDashboard => 'Dashboard paie',
            self::PayslipPdf => 'Export PDF bulletins',

            // 10. Présence & Sécurité
            self::ExitAuthorizations => 'Autorisations de sortie',
            self::SecurityPatrols => 'Rondes de surveillance',
            self::PresenceAlerts => 'Alertes présence',

            // 11. Géofencing
            self::Geofencing => 'Zones géographiques',
            self::GpsTracking => 'Tracking GPS temps réel',
            self::GeofenceAlerts => 'Alertes géofencing',

            // 12. IoT
            self::IotSensors => 'Capteurs IoT',
            self::IotDashboard => 'Dashboard IoT',

            // 13. IA
            self::AiPredictions => 'Prédictions & IA',

            // 14. Météo
            self::WeatherIntegration => 'Intégration météo',

            // 15. Comparaison
            self::MultiFarmComparison => 'Comparaison multi-fermes',

            // 16. Dashboard & Rapports
            self::MainDashboard => 'Dashboard principal',
            self::Reports => 'Rapports détaillés',
            self::ExcelExports => 'Exports Excel',
            self::PdfTemplates => 'Templates PDF',

            // 17. Notifications
            self::Notifications => 'Notifications in-app',
            self::EmailNotifications => 'Notifications email',
            self::SmsNotifications => 'Notifications SMS',
            self::DailyReport => 'Rapport quotidien',

            // 18. API
            self::ApiAccess => 'Accès API mobile',

            // 19. Utilisateurs
            self::TeamManagement => 'Gestion d\'équipe',
            self::Invitations => 'Invitations',

            // 20. Scheduler
            self::Scheduler => 'Tâches planifiées',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::MultiFarms, self::Buildings, self::Suppliers, self::FarmSettings => 'Fermes & Infrastructure',
            self::Flocks, self::DailyRecords, self::ProductionPhases, self::ZootechnicalCalculations, self::ProductionAlerts => 'Production & Cycles',
            self::HealthPrograms, self::Vaccinations, self::VetVisits, self::HealthIncidents, self::FarmJournal => 'Santé & Vaccinations',
            self::StockManagement, self::StockBatches, self::PurchaseOrders, self::Inventories, self::StockAlerts => 'Gestion des Stocks',
            self::Customers, self::Invoices, self::Quotes, self::CreditNotes, self::Payments, self::Deliveries, self::SalesAlerts, self::SalesDashboard => 'Ventes & Clients',
            self::Accounting, self::Transfers, self::BankReconciliation, self::FinancialAudit, self::FinanceDashboard, self::Expenses => 'Comptabilité & Finances',
            self::Equipment, self::EquipmentMaintenance, self::Depreciation => 'Équipements & Matériel',
            self::Employees, self::Contracts, self::LeaveManagement, self::Attendance, self::HrDashboard => 'Ressources Humaines',
            self::Payroll, self::CnpsCalculation, self::PayrollDashboard, self::PayslipPdf => 'Paie & CNPS',
            self::ExitAuthorizations, self::SecurityPatrols, self::PresenceAlerts => 'Présence & Sécurité',
            self::Geofencing, self::GpsTracking, self::GeofenceAlerts => 'Géofencing & GPS',
            self::IotSensors, self::IotDashboard => 'Système IoT',
            self::AiPredictions => 'Prédictions & IA',
            self::WeatherIntegration => 'Météo',
            self::MultiFarmComparison => 'Comparaison Multi-Fermes',
            self::MainDashboard, self::Reports, self::ExcelExports, self::PdfTemplates => 'Dashboard & Rapports',
            self::Notifications, self::EmailNotifications, self::SmsNotifications, self::DailyReport => 'Alertes & Notifications',
            self::ApiAccess => 'API Mobile',
            self::TeamManagement, self::Invitations => 'Utilisateurs & Abonnements',
            self::Scheduler => 'Tâches Planifiées',
        };
    }

    /**
     * Options groupées pour CheckboxList.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Options groupées par catégorie.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $case) => $case->group())
            ->map(fn ($cases) => $cases->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all())
            ->all();
    }
}
