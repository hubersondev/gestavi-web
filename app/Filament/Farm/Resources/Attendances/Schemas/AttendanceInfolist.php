<?php

namespace App\Filament\Farm\Resources\Attendances\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employé & Statut')
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employé')
                            ->weight('bold'),

                        TextEntry::make('attendance_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(3),

                Section::make('Horaires')
                    ->schema([
                        TextEntry::make('check_in')
                            ->label('Heure d\'arrivée')
                            ->placeholder('—'),

                        TextEntry::make('check_out')
                            ->label('Heure de départ')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Détails')
                    ->schema([
                        TextEntry::make('late_minutes')
                            ->label('Minutes de retard')
                            ->suffix(' min')
                            ->hidden(fn ($record) => ! $record->late_minutes),

                        TextEntry::make('overtime_hours')
                            ->label('Heures supplémentaires')
                            ->suffix(' h')
                            ->hidden(fn ($record) => ! $record->overtime_hours),
                    ])
                    ->columns(2),

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
