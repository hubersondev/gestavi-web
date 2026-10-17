<?php

namespace App\Filament\Pages;

use App\Filament\Audit\AuditLogTable;
use App\Models\AuditLog as AuditLogEntry;
use App\Models\Organisation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Journal d'audit de toutes les organisations, pour le support de la plateforme : mêmes
 * colonnes que dans le panel Ferme, plus la structure concernée et son filtre.
 */
class AuditLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?string $navigationLabel = 'Journal d\'audit';

    protected static ?string $title = 'Journal d\'audit des organisations';

    protected static ?string $slug = 'journal-audit';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.audit-log';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermission('organisations.view');
    }

    public function table(Table $table): Table
    {
        $table = AuditLogTable::configure($table, AuditLogEntry::query(), withOrganisation: true);

        return $table->filters([
            SelectFilter::make('organisation_id')
                ->label('Structure')
                ->options(fn (): array => Organisation::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            ...$table->getFilters(),
        ]);
    }
}
