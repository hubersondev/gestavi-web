<?php

namespace App\Filament\Farm\Resources\Deliveries\Schemas;

use App\Enums\DeliveryStatus;
use App\Enums\SaleStatus;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Models\Sale;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Livraison')
                    ->columns(2)
                    ->schema([
                        TextInput::make('delivery_number')
                            ->label('N° bon de livraison')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Généré automatiquement (BL-AAAA-XXXXX)'),

                        Select::make('sale_id')
                            ->label('Facture')
                            ->options(function (): array {
                                $query = Sale::query()
                                    ->with('customer')
                                    ->where('organisation_id', Filament::getTenant()?->getKey())
                                    ->where('status', '!=', SaleStatus::Cancelled->value)
                                    ->orderByDesc('sale_date');

                                return app(CurrentFarm::class)->restrictForCurrentUser($query, 'farm_id', allowOrganisationLevel: true)
                                    ->limit(200)
                                    ->get()
                                    ->mapWithKeys(fn (Sale $s) => [$s->id => $s->invoice_number.' — '.($s->customer?->name ?? 'Client comptant')])
                                    ->toArray();
                            })
                            ->rules(fn (): array => [Rule::exists('sales', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $sale = $state ? Sale::query()->with('customer')->find($state) : null;

                                if ($sale?->customer) {
                                    $set('recipient_name', $sale->customer->name);
                                    $set('recipient_phone', $sale->customer->phone);
                                    $set('delivery_address', $sale->customer->address);
                                }
                            })
                            ->disabledOn('edit'),

                        FiscalYearDatePicker::make('delivery_date')
                            ->label('Date de livraison prévue')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('status')
                            ->label('Statut')
                            ->options(DeliveryStatus::class)
                            ->default(DeliveryStatus::Planned)
                            ->required()
                            ->native(false)
                            ->disabled()
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Piloté par les actions « Départ » et « Marquer livrée »'),

                        TextInput::make('driver_name')->label('Livreur')->maxLength(255),
                        TextInput::make('vehicle')->label('Véhicule')->maxLength(100)->placeholder('Immatriculation, type'),
                        TextInput::make('recipient_name')->label('Destinataire')->maxLength(255),
                        TextInput::make('recipient_phone')->label('Téléphone destinataire')->tel()->maxLength(50),
                        TextInput::make('delivery_address')->label('Adresse de livraison')->maxLength(255)->columnSpanFull(),
                        Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }
}
