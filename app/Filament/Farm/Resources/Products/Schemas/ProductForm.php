<?php

namespace App\Filament\Farm\Resources\Products\Schemas;

use App\Enums\ProductKind;
use App\Models\Stock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produit')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Désignation')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('organisation_id', Filament::getTenant()?->getKey())),

                        TextInput::make('code')
                            ->label('Code')
                            ->maxLength(50),

                        Select::make('kind')
                            ->label('Type')
                            ->options(ProductKind::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $kind = $state instanceof ProductKind ? $state : ($state ? ProductKind::tryFrom((string) $state) : null);

                                if ($kind) {
                                    $set('is_live_birds', $kind->decrementsFlock());
                                }
                            }),

                        TextInput::make('unit')
                            ->label('Unité de vente')
                            ->required()
                            ->default('unité')
                            ->maxLength(30)
                            ->placeholder('plateau, sujet, kg, sac…'),

                        TextInput::make('default_unit_price')
                            ->label('Prix unitaire par défaut')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('XOF'),

                        TextInput::make('default_tax_percent')
                            ->label('TVA par défaut')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%'),

                        Toggle::make('is_live_birds')
                            ->label('Sujets vivants')
                            ->helperText('La vente de ce produit décrémente l\'effectif de la bande liée')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),

                        Select::make('stock_id')
                            ->label('Stock associé (produit stocké)')
                            ->helperText('Ex. poulets abattus : le stock alimenté par les abattages')
                            ->options(fn (): array => app(CurrentFarm::class)
                                ->restrictForCurrentUser(Stock::query()->where('organisation_id', Filament::getTenant()?->getKey())->active(), 'farm_id', allowOrganisationLevel: true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->rules(fn (): array => [Rule::exists('stocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->searchable()
                            ->nullable()
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
