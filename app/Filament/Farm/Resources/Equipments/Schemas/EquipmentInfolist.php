<?php

namespace App\Filament\Farm\Resources\Equipments\Schemas;

use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('inventory_number')
                            ->label('N° inventaire')
                            ->placeholder('-'),

                        TextEntry::make('name')
                            ->label('Nom'),

                        TextEntry::make('serial_number')
                            ->label('N° de série')
                            ->placeholder('-'),

                        TextEntry::make('building.farm.name')
                            ->label('Ferme')
                            ->placeholder('Non assigné')
                            ->hidden(function () {
                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                return $membership && app(CurrentFarm::class)->get($membership) !== null;
                            }),

                        TextEntry::make('building.name')
                            ->label('Bâtiment')
                            ->placeholder('Non assigné'),

                        TextEntry::make('category')
                            ->label('Catégorie')
                            ->formatStateUsing(fn ($record) => $record->getCategoryLabel()),

                        TextEntry::make('brand')
                            ->label('Marque')
                            ->placeholder('-'),

                        TextEntry::make('model')
                            ->label('Modèle')
                            ->placeholder('-'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                            ->color(fn ($state) => match ($state) {
                                'in_service' => 'success',
                                'in_stock' => 'info',
                                'in_maintenance' => 'warning',
                                'out_of_service' => 'danger',
                                'disposed' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('condition')
                            ->label('État')
                            ->badge()
                            ->formatStateUsing(fn ($record) => $record->getConditionLabel())
                            ->color(fn ($state) => match ($state) {
                                'new' => 'success',
                                'good' => 'info',
                                'fair' => 'warning',
                                'poor', 'broken' => 'danger',
                                default => 'gray',
                            })
                            ->placeholder('-'),
                    ]),

                Section::make('Capacité & Fournisseur')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('capacity')
                            ->label('Capacité')
                            ->formatStateUsing(fn ($record) => $record->capacity
                                ? $record->capacity.' '.($record->capacity_unit ?? '')
                                : null)
                            ->placeholder('-'),

                        TextEntry::make('supplier.name')
                            ->label('Fournisseur')
                            ->placeholder('-'),

                        TextEntry::make('purchase_reference')
                            ->label('Réf. achat')
                            ->placeholder('-'),
                    ]),

                Section::make('Achat & Valeur')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('purchase_date')
                            ->label('Date d\'achat')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('purchase_price')
                            ->label('Prix d\'achat')
                            ->money('XOF')
                            ->placeholder('-'),

                        TextEntry::make('current_value')
                            ->label('Valeur actuelle')
                            ->money('XOF')
                            ->placeholder('-'),

                        TextEntry::make('warranty_expiry_date')
                            ->label('Fin de garantie')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('lifespan_years')
                            ->label('Durée de vie')
                            ->suffix(' ans')
                            ->placeholder('-'),
                    ]),

                Section::make('Maintenance')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_maintenance_date')
                            ->label('Dernière maintenance')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('next_maintenance_date')
                            ->label('Prochaine maintenance')
                            ->date()
                            ->placeholder('-'),

                        TextEntry::make('maintenance_interval_days')
                            ->label('Intervalle')
                            ->suffix(' jours')
                            ->placeholder('-'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Dates système')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Dernière modification')
                            ->dateTime(),
                    ]),
            ]);
    }
}
