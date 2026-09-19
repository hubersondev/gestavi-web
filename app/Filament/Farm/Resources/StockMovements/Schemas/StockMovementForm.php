<?php

namespace App\Filament\Farm\Resources\StockMovements\Schemas;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Flock;
use App\Models\Stock;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mouvement')
                    ->columns(2)
                    ->schema([
                        Select::make('stock_id')
                            ->label('Produit')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Stock::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->active()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit'),

                        Select::make('type')
                            ->label('Type')
                            ->options(StockMovementType::class)
                            ->required()
                            ->native(false),

                        Select::make('reason')
                            ->label('Motif')
                            ->options(StockMovementReason::class)
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('quantity')
                            ->label('Quantité')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),

                        TextInput::make('unit_price')
                            ->label('Prix unitaire')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('XOF'),

                        DatePicker::make('movement_date')
                            ->label('Date du mouvement')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('flock_id')
                            ->label('Bande')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Flock::query()
                                    ->whereHas('building.farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                    ->orderByDesc('start_date')
                                    ->get()
                                    ->mapWithKeys(fn (Flock $flock) => [$flock->id => $flock->code.' – '.$flock->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->nullable(),

                        TextInput::make('reference')
                            ->label('Référence')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
