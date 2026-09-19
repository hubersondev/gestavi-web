<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanFeature;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('monthly_price')
                            ->label('Prix mensuel')
                            ->money('XOF'),
                        TextEntry::make('yearly_price')
                            ->label('Prix annuel')
                            ->money('XOF')
                            ->placeholder('-'),
                        TextEntry::make('duration_months')
                            ->label('Durée')
                            ->suffix(' mois'),
                        TextEntry::make('trial_days')
                            ->label("Période d'essai")
                            ->suffix(' jours'),
                        TextEntry::make('max_farms')
                            ->label('Max fermes')
                            ->placeholder('Illimité'),
                        TextEntry::make('max_users')
                            ->label('Max utilisateurs')
                            ->placeholder('Illimité'),
                        TextEntry::make('sort_order')
                            ->label("Ordre d'affichage"),
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),
                Section::make('Fonctionnalités actives')
                    ->schema([
                        TextEntry::make('features')
                            ->label('')
                            ->badge()
                            ->state(fn ($record): array => collect($record->features ?? [])
                                ->map(fn (string $key): string => PlanFeature::tryFrom($key)?->label() ?? $key)
                                ->all())
                            ->placeholder('Aucune fonctionnalité'),
                    ]),
                Section::make('Dates')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('Date de modification')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
