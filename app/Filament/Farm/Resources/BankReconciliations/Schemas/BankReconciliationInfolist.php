<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Schemas;

use App\Filament\Farm\Resources\BankReconciliations\Pages\ViewBankReconciliation;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankReconciliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Relevé')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('account.name')
                            ->label('Compte')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('statement_date')
                            ->label('Date du relevé')
                            ->date('d/m/Y'),

                        TextEntry::make('statement_balance')
                            ->label('Solde du relevé')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        TextEntry::make('creator.name')
                            ->label('Ouvert par')
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label('Clôturé le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('completer.name')
                            ->label('Clôturé par')
                            ->placeholder('—'),

                        TextEntry::make('statement_file')
                            ->label('Relevé importé')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Synthèse')
                    ->description('Le rapprochement se clôture quand le solde pointé (solde initial et mouvements pointés jusqu\'à la date du relevé) égale le solde du relevé, à moins d\'un franc près. Une fois clôturé, ce solde est figé.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('summary_reconciled_balance')
                            ->label('Solde pointé')
                            ->state(fn (ViewBankReconciliation $livewire): string => static::money($livewire->summary()['reconciled_balance']))
                            ->weight('bold'),

                        TextEntry::make('summary_difference')
                            ->label('Écart relevé − pointé')
                            ->state(fn (ViewBankReconciliation $livewire): string => static::money($livewire->summary()['difference']))
                            ->badge()
                            ->color(fn (ViewBankReconciliation $livewire): string => $livewire->summary()['is_balanced'] ? 'success' : 'danger'),

                        TextEntry::make('summary_pending')
                            ->label('Transactions à pointer')
                            ->state(function (ViewBankReconciliation $livewire, BankReconciliation $record): string {
                                if ($record->isCompleted()) {
                                    return '—';
                                }

                                $summary = $livewire->summary();

                                return $summary['pending_count'].' ('.static::money($summary['pending_amount']).')';
                            }),

                        TextEntry::make('summary_lines')
                            ->label('Lignes du relevé')
                            ->state(function (ViewBankReconciliation $livewire): string {
                                $summary = $livewire->summary();

                                if ($summary['lines_total'] === 0) {
                                    return 'Aucune ligne importée';
                                }

                                return sprintf('%d rapprochée(s) · %d à rapprocher · %d ignorée(s)', $summary['lines_matched'], $summary['lines_unmatched'], $summary['lines_ignored']);
                            }),
                    ]),
            ]);
    }

    private static function money(float $amount): string
    {
        return BankReconciliationService::money($amount);
    }
}
