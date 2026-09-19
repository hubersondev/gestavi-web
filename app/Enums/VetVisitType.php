<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VetVisitType: string implements HasColor, HasIcon, HasLabel
{
    case Routine = 'routine';
    case Emergency = 'emergency';
    case FollowUp = 'follow_up';
    case Vaccination = 'vaccination';
    case Diagnostic = 'diagnostic';
    case Consultation = 'consultation';
    case Autopsy = 'autopsy';
    case Certification = 'certification';

    public function getLabel(): string
    {
        return match ($this) {
            self::Routine => 'Visite de routine',
            self::Emergency => 'Urgence',
            self::FollowUp => 'Visite de suivi',
            self::Vaccination => 'Vaccination',
            self::Diagnostic => 'Diagnostic',
            self::Consultation => 'Consultation',
            self::Autopsy => 'Autopsie',
            self::Certification => 'Certification',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Routine => 'success',
            self::Emergency => 'danger',
            self::FollowUp => 'warning',
            self::Vaccination => 'info',
            self::Diagnostic => 'primary',
            self::Consultation => 'gray',
            self::Autopsy => 'danger',
            self::Certification => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Routine => 'heroicon-o-check-circle',
            self::Emergency => 'heroicon-o-exclamation-triangle',
            self::FollowUp => 'heroicon-o-arrow-path',
            self::Vaccination => 'heroicon-o-shield-check',
            self::Diagnostic => 'heroicon-o-magnifying-glass',
            self::Consultation => 'heroicon-o-chat-bubble-left-ellipsis',
            self::Autopsy => 'heroicon-o-document-magnifying-glass',
            self::Certification => 'heroicon-o-clipboard-document-check',
        };
    }
}
