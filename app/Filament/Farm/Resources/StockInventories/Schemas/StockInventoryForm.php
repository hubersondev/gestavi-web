<?php

namespace App\Filament\Farm\Resources\StockInventories\Schemas;

use App\Enums\StockInventoryDifferenceReason;
use App\Enums\StockInventoryStatus;
use App\Models\Stock;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StockInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('inventory_ref')
                            ->label('Référence')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Générée automatiquement (INV-YYYY-XXXXX)'),

                        DatePicker::make('inventory_date')
                            ->label('Date de l\'inventaire')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('stock_id')
                            ->label('Produit inventorié')
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
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state) {
                                    $stock = Stock::find($state);
                                    if ($stock) {
                                        $set('theoretical_quantity', (float) $stock->quantity);
                                        $set('unit_price', (float) $stock->unit_price);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Quantités')
                    ->columns(3)
                    ->schema([
                        TextInput::make('theoretical_quantity')
                            ->label('Quantité théorique (système)')
                            ->numeric()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Pré-remplie depuis le stock'),

                        TextInput::make('physical_quantity')
                            ->label('Quantité physique (comptée)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->helperText('Résultat du comptage sur le terrain'),

                        TextInput::make('unit_price')
                            ->label('Prix unitaire')
                            ->numeric()
                            ->suffix('XOF')
                            ->disabled()
                            ->dehydrated(),

                        Placeholder::make('difference_preview')
                            ->label('Écart calculé')
                            ->content(function (Get $get): string {
                                $theoretical = (float) ($get('theoretical_quantity') ?? 0);
                                $physical = (float) ($get('physical_quantity') ?? 0);
                                $diff = $physical - $theoretical;
                                $sign = $diff > 0 ? '+' : '';

                                return $sign.rtrim(rtrim(number_format($diff, 2, ',', ' '), '0'), ',');
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Justification')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(StockInventoryStatus::class)
                            ->default(StockInventoryStatus::Draft)
                            ->required()
                            ->native(false),

                        Select::make('difference_reason')
                            ->label('Motif de l\'écart')
                            ->options(StockInventoryDifferenceReason::class)
                            ->native(false)
                            ->nullable(),

                        Textarea::make('justification')
                            ->label('Justification détaillée')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes internes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
