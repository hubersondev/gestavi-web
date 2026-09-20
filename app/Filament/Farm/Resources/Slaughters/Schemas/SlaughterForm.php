<?php

namespace App\Filament\Farm\Resources\Slaughters\Schemas;

use App\Models\Flock;
use App\Models\Stock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class SlaughterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Abattage')
                    ->columns(2)
                    ->schema([
                        Select::make('flock_id')
                            ->label('Bande')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();
                                $query = Flock::query()
                                    ->where('flocks.organisation_id', $tenant?->getKey())
                                    ->active()
                                    ->orderByDesc('start_date');

                                return app(CurrentFarm::class)->restrictForCurrentUser($query, 'flocks.farm_id')
                                    ->get()
                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.' — '.number_format((int) $f->quantity_current, 0, ',', ' ').' sujets présents'])
                                    ->toArray();
                            })
                            ->rules(fn (): array => [Rule::exists('flocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->required()
                            ->searchable()
                            ->live()
                            ->disabledOn('edit')
                            ->columnSpanFull(),

                        DatePicker::make('slaughter_date')
                            ->label('Date d\'abattage')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        TextInput::make('birds_count')
                            ->label('Sujets abattus')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(function (Get $get, $record): ?int {
                                $flock = Flock::query()->find($get('flock_id'));

                                return $flock ? (int) $flock->quantity_current + (int) ($record?->birds_count ?? 0) : null;
                            })
                            ->helperText('Ne peut pas dépasser l\'effectif présent de la bande'),

                        TextInput::make('live_weight_kg')
                            ->label('Poids vif total')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('kg'),

                        TextInput::make('carcass_weight_kg')
                            ->label('Poids carcasses total')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('kg')
                            ->helperText('Le rendement carcasse est calculé automatiquement'),

                        Select::make('destination_stock_id')
                            ->label('Stock de destination des carcasses')
                            ->helperText('Optionnel : entrée de stock en kg (si l\'unité du stock est le kg) ou en sujets')
                            ->options(function (): array {
                                $query = Stock::query()
                                    ->where('organisation_id', Filament::getTenant()?->getKey())
                                    ->active()
                                    ->orderBy('name');

                                return app(CurrentFarm::class)->restrictForCurrentUser($query, 'farm_id', allowOrganisationLevel: true)
                                    ->get()
                                    ->mapWithKeys(fn (Stock $s) => [$s->id => $s->name.' ('.$s->unit.')'])
                                    ->toArray();
                            })
                            ->rules(fn (): array => [Rule::exists('stocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->searchable()
                            ->nullable()
                            ->disabledOn('edit')
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
