<?php

namespace App\Filament\Farm\Resources\Leaves\Schemas;

use App\Enums\LeaveStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeaveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Congé')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employé')
                            ->weight('bold'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->color(fn ($state) => $state->color()),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn ($state) => $state->color()),
                    ]),

                Section::make('Période')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Date de début')
                            ->date('d/m/Y'),

                        TextEntry::make('end_date')
                            ->label('Date de fin')
                            ->date('d/m/Y'),

                        TextEntry::make('days_count')
                            ->label('Nombre de jours')
                            ->suffix(' jours'),
                    ]),

                Section::make('Motif')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Motif')
                            ->placeholder('Aucun motif renseigné')
                            ->columnSpanFull(),

                        TextEntry::make('rejection_reason')
                            ->label('Motif du refus')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->hidden(fn ($record): bool => ! $record->isRejected()),
                    ]),

                Section::make('Approbation')
                    ->columns(2)
                    ->hidden(fn ($record): bool => $record->status === LeaveStatus::Pending)
                    ->schema([
                        TextEntry::make('approver.name')
                            ->label('Approbateur')
                            ->placeholder('—'),

                        TextEntry::make('approved_at')
                            ->label('Date de décision')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Justificatif')
                    ->hidden(fn ($record): bool => blank($record->attachment))
                    ->schema([
                        TextEntry::make('attachment')
                            ->label('Document')
                            ->formatStateUsing(fn ($state): string => basename((string) $state))
                            ->url(fn ($state): ?string => $state ? \Illuminate\Support\Facades\Storage::url($state) : null, shouldOpenInNewTab: true)
                            ->icon('heroicon-o-paper-clip')
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('Aucune note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
