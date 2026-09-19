<?php

namespace App\Filament\Farm\Resources\CreditNotes\Schemas;

use App\Enums\CreditNoteStatus;
use App\Models\Sale;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreditNoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Avoir')
                    ->columns(2)
                    ->schema([
                        TextInput::make('credit_note_number')
                            ->label('N° Avoir')
                            ->maxLength(50)
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Généré automatiquement (AV-YYYY-XXXXX)'),

                        Select::make('sale_id')
                            ->label('Facture concernée')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Sale::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                    ->whereNotIn('status', ['cancelled', 'draft'])
                                    ->orderByDesc('sale_date')
                                    ->get()
                                    ->mapWithKeys(fn (Sale $sale) => [
                                        $sale->id => "{$sale->invoice_number} — ".($sale->customer?->name ?? 'Comptant').' ('.number_format((float) $sale->total, 0, ',', ' ').' XOF)',
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit'),

                        DatePicker::make('issue_date')
                            ->label('Date d\'émission')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        TextInput::make('amount')
                            ->label('Montant de l\'avoir')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('XOF')
                            ->helperText('Ne peut pas dépasser le total de la facture'),

                        Select::make('status')
                            ->label('Statut')
                            ->options(CreditNoteStatus::class)
                            ->default(CreditNoteStatus::Draft)
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('reason')
                            ->label('Motif')
                            ->maxLength(255)
                            ->placeholder('Ex : Produit retourné, erreur de facturation…')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description détaillée')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
