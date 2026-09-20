<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DeliveryStatus: string implements HasColor, HasIcon, HasLabel
{
    case Planned = 'planned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Planifiée',
            self::InTransit => 'En cours',
            self::Delivered => 'Livrée',
            self::Failed => 'Échouée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InTransit => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Planned => 'heroicon-o-calendar',
            self::InTransit => 'heroicon-o-truck',
            self::Delivered => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
        };
    }
}
