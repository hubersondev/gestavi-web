<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganisationsRelationManager extends RelationManager
{
    protected static string $relationship = 'organisations';

    protected static ?string $title = 'Organisations';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pivot.role')
                    ->label('Rôle')
                    ->badge(),
                TextColumn::make('pivot.status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('pivot.joined_at')
                    ->label("Date d'adhésion")
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
