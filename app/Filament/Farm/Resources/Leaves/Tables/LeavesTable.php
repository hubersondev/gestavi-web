<?php

namespace App\Filament\Farm\Resources\Leaves\Tables;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Farm\Resources\Leaves\LeaveResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeavesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Employé')
                    ->state(fn ($record): string => $record->employee?->full_name ?? '—')
                    ->searchable(query: fn ($query, $search) => $query->whereHas(
                        'employee',
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    )),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_count')
                    ->label('Jours')
                    ->numeric()
                    ->suffix(' j')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(LeaveType::class),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(LeaveStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => $record->isPending() && LeaveResource::canEdit($record)),
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approuver le congé')
                    ->modalDescription('Le congé sera approuvé et l\'employé en sera informé.')
                    ->visible(fn ($record) => $record->isPending() && LeaveResource::canPerformAction('approve'))
                    ->action(fn ($record) => $record->approve(auth()->id())),
                Action::make('reject')
                    ->label('Refuser')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Refuser le congé')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motif du refus')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn ($record) => $record->isPending() && LeaveResource::canPerformAction('reject'))
                    ->action(fn ($record, array $data) => $record->reject($data['reason'], auth()->id())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => LeaveResource::canDeleteAny()),
                ]),
            ]);
    }
}
