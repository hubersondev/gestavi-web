<?php

namespace App\Filament\Farm\Resources\Attendances\Tables;

use App\Enums\AttendanceStatus;
use App\Filament\Farm\Resources\Attendances\AttendanceResource;
use App\Services\CurrentFiscalYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'attendance_date')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employé')
                    ->state(fn ($record) => $record->employee?->full_name)
                    ->searchable(['employees.first_name', 'employees.last_name'])
                    ->sortable(),

                TextColumn::make('attendance_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),

                TextColumn::make('check_in')
                    ->label('Arrivée')
                    ->placeholder('—'),

                TextColumn::make('check_out')
                    ->label('Départ')
                    ->placeholder('—'),

                TextColumn::make('late_minutes')
                    ->label('Retard')
                    ->suffix(' min')
                    ->hidden(fn ($record) => ! $record?->late_minutes)
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('overtime_hours')
                    ->label('Heures sup.')
                    ->suffix(' h')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('attendance_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AttendanceStatus::class),

                Filter::make('attendance_date')
                    ->label('Date')
                    ->form([
                        DatePicker::make('from')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('attendance_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('attendance_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => AttendanceResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => AttendanceResource::canDeleteAny()),
                ]),
            ]);
    }
}
