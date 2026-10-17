<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionCategory: string implements HasColor, HasLabel
{
    case Opening = 'opening';
    case Sale = 'sale';
    case Payment = 'payment';
    case Expense = 'expense';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
    case Payroll = 'payroll';
    case Refund = 'refund';
    case Theft = 'theft';
    case Contribution = 'contribution';
    case Withdrawal = 'withdrawal';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Ouverture',
            self::Sale => 'Vente',
            self::Payment => 'Encaissement',
            self::Expense => 'Dépense',
            self::TransferIn => 'Transfert entrant',
            self::TransferOut => 'Transfert sortant',
            self::Adjustment => 'Ajustement',
            self::Payroll => 'Paie',
            self::Refund => 'Remboursement',
            self::Theft => 'Vol / perte de caisse',
            self::Contribution => 'Apport de fonds',
            self::Withdrawal => 'Prélèvement de l\'exploitant',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Opening => 'gray',
            self::Sale => 'success',
            self::Payment => 'success',
            self::Expense => 'danger',
            self::TransferIn => 'info',
            self::TransferOut => 'warning',
            self::Adjustment => 'gray',
            self::Payroll => 'danger',
            self::Refund => 'info',
            self::Theft => 'danger',
            self::Contribution => 'success',
            self::Withdrawal => 'warning',
            self::Other => 'gray',
        };
    }
}
