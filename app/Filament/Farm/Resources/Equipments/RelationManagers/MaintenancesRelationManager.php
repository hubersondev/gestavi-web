<?php

namespace App\Filament\Farm\Resources\Equipments\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenancesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenances';

    protected static ?string $title = 'Maintenances';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            Section::make('Informations')
                ->schema([
                    TextInput::make('title')
                        ->label('Titre')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Révision annuelle')
                        ->columnSpan(2),

                    Select::make('maintenance_type')
                        ->label('Type')
                        ->required()
                        ->options([
                            'preventive' => 'Préventive',
                            'corrective' => 'Corrective',
                            'inspection' => 'Inspection',
                            'cleaning' => 'Nettoyage',
                            'calibration' => 'Calibration',
                            'replacement' => 'Remplacement',
                        ])
                        ->native(false),

                    Select::make('status')
                        ->label('Statut')
                        ->required()
                        ->options([
                            'scheduled' => 'Planifiée',
                            'in_progress' => 'En cours',
                            'completed' => 'Terminée',
                            'cancelled' => 'Annulée',
                        ])
                        ->default('scheduled')
                        ->native(false),

                    Select::make('condition_before')
                        ->label('État avant')
                        ->options([
                            'new' => 'Neuf',
                            'good' => 'Bon état',
                            'fair' => 'État moyen',
                            'poor' => 'Mauvais état',
                            'broken' => 'Cassé',
                        ])
                        ->native(false),

                    Select::make('condition_after')
                        ->label('État après')
                        ->options([
                            'new' => 'Neuf',
                            'good' => 'Bon état',
                            'fair' => 'État moyen',
                            'poor' => 'Mauvais état',
                            'broken' => 'Cassé',
                        ])
                        ->native(false),
                ])
                ->columns(2),

            Section::make('Dates')
                ->schema([
                    DatePicker::make('scheduled_date')
                        ->label('Date planifiée')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('performed_date')
                        ->label('Date réalisée')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('next_maintenance_date')
                        ->label('Prochaine maintenance')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->columns(3),

            Section::make('Coûts')
                ->schema([
                    TextInput::make('labor_cost')
                        ->label('Main d\'œuvre')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA'),

                    TextInput::make('parts_cost')
                        ->label('Pièces')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA'),

                    TextInput::make('total_cost')
                        ->label('Total')
                        ->numeric()
                        ->suffix('FCFA')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Main d\'œuvre + Pièces'),
                ])
                ->columns(3),

            Section::make('Intervenant externe')
                ->schema([
                    TextInput::make('external_technician')
                        ->label('Technicien')
                        ->maxLength(100)
                        ->placeholder('Nom du technicien'),

                    TextInput::make('external_company')
                        ->label('Société')
                        ->maxLength(100)
                        ->placeholder('Nom de la société'),
                ])
                ->columns(2)
                ->collapsed(),

            Section::make('Notes')
                ->schema([
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_date')
                    ->label('Date planifiée')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('maintenance_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->getTypeLabel())
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn ($record) => $record->getStatusColor()),

                TextColumn::make('total_cost')
                    ->label('Coût')
                    ->money('XOF')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('performed_date')
                    ->label('Réalisée le')
                    ->date()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('scheduled_date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Démarrer')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isScheduled())
                    ->action(fn ($record) => $record->start()),

                Action::make('complete')
                    ->label('Terminer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isInProgress())
                    ->action(fn ($record) => $record->complete()),

                Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isScheduled() || $record->isInProgress())
                    ->action(fn ($record) => $record->cancel()),
            ]);
    }
}
