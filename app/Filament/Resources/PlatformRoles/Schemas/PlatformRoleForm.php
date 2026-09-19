<?php

namespace App\Filament\Resources\PlatformRoles\Schemas;

use App\Models\PlatformPermission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlatformRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du rôle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(50)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')
                            ->label('Identifiant')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Identifiant technique unique du rôle'),
                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_system')
                            ->label('Rôle système')
                            ->helperText('Les rôles système ne peuvent pas être supprimés')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Section::make('Permissions')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship(titleAttribute: 'name')
                            ->columns(4)
                            ->gridDirection('row')
                            ->descriptions(
                                PlatformPermission::pluck('description', 'id')->toArray()
                            ),
                    ]),
            ]);
    }
}
