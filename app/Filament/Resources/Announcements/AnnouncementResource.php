<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementLevel;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\Organisation;
use App\Models\Plan;
use App\Policies\AnnouncementPolicy;
use App\Services\AnnouncementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Annonces aux organisations : bandeau dans leur espace pendant la période, et envoi en
 * notification aux responsables à la demande.
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static UnitEnum|string|null $navigationGroup = 'Clients';

    protected static ?string $modelLabel = 'Annonce';

    protected static ?string $pluralModelLabel = 'Annonces';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $policy = AnnouncementPolicy::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Titre')->required()->maxLength(150)->columnSpanFull(),
                    Textarea::make('body')->label('Texte')->required()->rows(4)->columnSpanFull()
                        ->helperText('Affiché tel quel dans l\'espace des organisations visées. Soyez concret : quoi, quand, ce que l\'utilisateur doit faire.'),
                    Select::make('level')->label('Importance')->options(AnnouncementLevel::class)->default(AnnouncementLevel::Info->value)->required()->native(false),
                    Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                ]),
            Section::make('Période et cible')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('starts_at')->label('Début d\'affichage')->default(now())->required()->native(false)->seconds(false),
                    DateTimePicker::make('ends_at')->label('Fin d\'affichage')->native(false)->seconds(false)->after('starts_at')->helperText('Vide : jusqu\'à désactivation.'),
                    Select::make('plan_id')
                        ->label('Limiter à un plan')
                        ->options(fn (): array => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->placeholder('Tous les plans')
                        ->native(false),
                    Select::make('organisation_id')
                        ->label('Limiter à une structure')
                        ->options(fn (): array => Organisation::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('Toutes les structures')
                        ->helperText('Prioritaire sur le plan.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Titre')->searchable()->weight('medium')->wrap(),
                TextColumn::make('level')->label('Importance')->badge(),
                TextColumn::make('target')->label('Cible')->state(fn (Announcement $record): string => $record->targetLabel())->color('gray'),
                TextColumn::make('starts_at')->label('Du')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('ends_at')->label('Au')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('notified_at')->label('Notifiée le')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                Action::make('notify')
                    ->label('Notifier les responsables')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Announcement $record): string => 'Les propriétaires et administrateurs de : '.mb_strtolower($record->targetLabel()).' recevront l\'annonce dans l\'application et par courriel selon leurs préférences.')
                    ->visible(fn (Announcement $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
                    ->action(function (Announcement $record): void {
                        $count = app(AnnouncementService::class)->notify($record, Filament::auth()->user());
                        Notification::make()->title($count.' responsable(s) notifié(s)')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
