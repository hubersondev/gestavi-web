<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Page de profil des deux panels. Par rapport à la page Filament d'origine, le mot de passe
 * actuel est demandé en premier et toujours visible dans le bloc de changement de mot de passe,
 * au lieu d'apparaître sous le nouveau mot de passe une fois celui-ci saisi.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->description('Changer l\'adresse e-mail demande aussi le mot de passe actuel.')
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make('Mot de passe')
                    ->description('Saisissez d\'abord votre mot de passe actuel, puis le nouveau et sa confirmation.')
                    ->schema([
                        $this->getCurrentPasswordFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ]),
            ]);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label('Mot de passe actuel')
            ->validationAttribute('mot de passe actuel')
            ->password()
            ->autocomplete('current-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required(fn (Get $get): bool => filled($get('password')) || ($get('email') !== $this->getUser()->getAttributeValue('email')))
            ->helperText('Obligatoire pour changer le mot de passe ou l\'adresse e-mail.')
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nouveau mot de passe')
            ->helperText('Au moins 8 caractères en développement ; 12 avec majuscules, chiffres et symboles en production.');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmer le nouveau mot de passe');
    }
}
