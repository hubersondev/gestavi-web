<?php

namespace App\Exceptions;

use Exception;

class AccountingException extends Exception
{
    public static function unbalancedEntry(float $debit, float $credit): self
    {
        return new self(sprintf(
            'Écriture déséquilibrée : débit %s XOF, crédit %s XOF.',
            number_format($debit, 0, ',', ' '),
            number_format($credit, 0, ',', ' '),
        ));
    }

    public static function periodClosed(string $date, string $fiscalYear): self
    {
        return new self("La date {$date} appartient à l'exercice « {$fiscalYear} » qui est clôturé : aucune écriture ni transaction ne peut plus y être enregistrée.");
    }

    public static function fiscalYearAlreadyClosed(string $fiscalYear): self
    {
        return new self("L'exercice « {$fiscalYear} » est déjà clôturé.");
    }

    public static function fiscalYearHasUnbalancedEntries(string $fiscalYear, int $count): self
    {
        return new self("L'exercice « {$fiscalYear} » contient {$count} écriture(s) déséquilibrée(s) : clôture impossible.");
    }

    public static function overlappingFiscalYear(string $label): self
    {
        return new self("Les dates chevauchent l'exercice existant « {$label} ».");
    }

    public static function unknownMapping(string $key): self
    {
        return new self("Aucun compte comptable n'est associé à la clé « {$key} » : complétez le paramétrage comptable.");
    }

    public static function ledgerCorrupted(string $entryNumber, string $reason): self
    {
        $label = match ($reason) {
            'missing_hash' => 'hash absent',
            'chain_break' => 'chaînage rompu avec l\'écriture précédente',
            'hash_mismatch' => 'contenu modifié après enregistrement',
            default => $reason,
        };

        return new self("Intégrité du ledger compromise à partir de l'écriture {$entryNumber} : {$label}. Rapprochez la base de la dernière sauvegarde saine et des ancrages.");
    }
}
