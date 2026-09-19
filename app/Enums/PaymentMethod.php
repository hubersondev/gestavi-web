<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case Cash = 'cash';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Wave = 'wave';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Credit = 'credit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Wave => 'Wave',
            self::BankTransfer => 'Virement bancaire',
            self::Cheque => 'Chèque',
            self::Credit => 'Crédit (à terme)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::OrangeMoney, self::MtnMomo, self::MoovMoney => 'warning',
            self::Wave => 'primary',
            self::BankTransfer => 'info',
            self::Cheque => 'gray',
            self::Credit => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Cash => 'heroicon-o-banknotes',
            self::OrangeMoney, self::MtnMomo, self::MoovMoney, self::Wave => 'heroicon-o-device-phone-mobile',
            self::BankTransfer => 'heroicon-o-building-library',
            self::Cheque => 'heroicon-o-document-text',
            self::Credit => 'heroicon-o-clock',
        };
    }
}
