<?php

namespace App\Filament\Farm\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditOrganisationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Profil de la structure';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan souscrit')
                    ->schema([
                        TextEntry::make('plan.name')
                            ->label('Plan actuel')
                            ->state(fn () => $this->tenant->plan?->name ?? 'Aucun plan')
                            ->badge()
                            ->color(fn () => $this->tenant->plan ? 'success' : 'gray'),
                    ]),
                Section::make('Informations')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email(),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20),
                        Textarea::make('address')
                            ->label('Adresse')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
