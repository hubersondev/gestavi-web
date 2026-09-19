<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Tables;

use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganisationRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Identifiant')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('weight')
                    ->label('Poids')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('members_count')
                    ->label('Membres')
                    ->counts('members')
                    ->sortable(),
                /*IconColumn::make('is_system')
                    ->label('Système')
                    ->boolean()
                    ->sortable(),*/
                TextColumn::make('created_at')
                    ->label('Date de création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('weight', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => OrganisationRoleResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn ($record): bool => ! $record->is_system && OrganisationRoleResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => OrganisationRoleResource::canDeleteAny()),
                ]),
            ]);
    }
}
