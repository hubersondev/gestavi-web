<?php

namespace App\Filament\Farm\Pages;

use App\Filament\Audit\AuditLogTable;
use App\Models\AuditLog;
use App\Models\Organisation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Journal d'audit de l'organisation : qui a créé, modifié ou supprimé quoi, et quand, sur les
 * enregistrements sensibles (trésorerie, ventes, stocks, production, paie…), avec le détail
 * avant / après. Réservé aux rôles à accès global (propriétaire, administrateur).
 */
class AuditLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Journal d\'audit';

    protected static ?string $title = 'Journal d\'audit';

    protected static ?string $slug = 'journal-audit';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.farm.pages.audit-log';

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();
        $membership = $tenant instanceof Organisation ? Filament::auth()->user()?->membershipIn($tenant) : null;

        return (bool) $membership?->canAccessAllFarms();
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        $organisationId = (int) $tenant?->getKey();

        return AuditLogTable::configure(
            $table,
            AuditLog::query()->forOrganisation($organisationId),
            causerOptions: fn (): array => $tenant instanceof Organisation
                ? $tenant->members()->orderBy('name')->pluck('users.name', 'users.id')->all()
                : [],
        );
    }
}
