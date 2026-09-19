<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeaveType: string implements HasLabel
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Maternity = 'maternity';
    case Paternity = 'paternity';
    case Unpaid = 'unpaid';
    case Exceptional = 'exceptional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Annual => 'Congé annuel',
            self::Sick => 'Congé maladie',
            self::Maternity => 'Congé maternité',
            self::Paternity => 'Congé paternité',
            self::Unpaid => 'Congé sans solde',
            self::Exceptional => 'Congé exceptionnel',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Annual => 'success',
            self::Sick => 'warning',
            self::Maternity => 'danger',
            self::Paternity => 'info',
            self::Unpaid => 'gray',
            self::Exceptional => 'warning',
        };
    }

    public function maxDays(): ?int
    {
        return match ($this) {
            self::Annual => 30,
            self::Sick => 15,
            self::Maternity => 14,
            self::Paternity => 5,
            self::Unpaid => null,
            self::Exceptional => 3,
        };
    }
}
