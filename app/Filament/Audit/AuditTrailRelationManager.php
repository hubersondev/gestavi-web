<?php

namespace App\Filament\Audit;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Onglet « Historique » d'une fiche : les entrées du journal d'audit de cet enregistrement
 * (relation `activities` du trait LogsAuditTrail).
 */
class AuditTrailRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Historique';

    protected static string|BackedEnum|null $icon = 'heroicon-o-finger-print';

    public function table(Table $table): Table
    {
        return AuditLogTable::configure($table, null, withSubject: false);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, 'activities');
    }
}
