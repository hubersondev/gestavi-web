<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use App\Models\SubscriptionPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Encaissements d'un abonnement, avec la facture PDF de chacun.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Encaissements et factures';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('Facture')->weight('medium'),
                TextColumn::make('paid_at')->label('Réglé le')->date('d/m/Y'),
                TextColumn::make('period')->label('Période')->state(fn (SubscriptionPayment $record): string => $record->periodLabel()),
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('billing_cycle')->label('Cycle')->badge()->color('gray'),
                TextColumn::make('amount')->label('Montant')->money('XOF', locale: 'fr')->alignRight(),
                TextColumn::make('payment_method')->label('Mode')->badge()->placeholder('—'),
                TextColumn::make('payment_reference')->label('Référence')->placeholder('—')->color('gray'),
            ])
            ->recordActions([
                Action::make('invoice')
                    ->label('Facture PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (SubscriptionPayment $record): StreamedResponse => self::download($record)),
            ])
            ->emptyStateHeading('Aucun encaissement')
            ->emptyStateDescription('Un essai gratuit ne génère pas de facture ; les souscriptions payées et les renouvellements apparaissent ici.');
    }

    public static function download(SubscriptionPayment $payment): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.subscription-invoice', ['payment' => $payment->load(['organisation.city', 'organisation.country'])])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'facture-'.$payment->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
