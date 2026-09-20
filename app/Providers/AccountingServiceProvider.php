<?php

namespace App\Providers;

use App\Enums\ExpenseStatus;
use App\Enums\PayrollStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Transaction;
use App\Services\AccountingService;
use Illuminate\Support\ServiceProvider;

/**
 * Branche la comptabilité sur les événements métier sans modifier les modèles :
 * chaque transaction, facture, dépense et bulletin validé génère son écriture,
 * et aucune écriture ne peut être datée dans un exercice clôturé.
 */
class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\AccountingMapper::class);
    }

    public function boot(): void
    {
        $accounting = fn (): AccountingService => $this->app->make(AccountingService::class);

        // Périodes clôturées : refus avant la base
        Transaction::creating(fn (Transaction $transaction) => $accounting()->assertPeriodOpen($transaction->organisation_id, $transaction->transaction_date));
        JournalEntry::creating(fn (JournalEntry $entry) => $accounting()->assertPeriodOpen($entry->organisation_id, $entry->entry_date));

        // Trésorerie : une transaction = une écriture
        Transaction::created(fn (Transaction $transaction) => $accounting()->postTransaction($transaction));

        // Ventes : comptabilisées dès que la facture quitte le brouillon, extournées à l'annulation
        Sale::created(function (Sale $sale) use ($accounting): void {
            if (! in_array($sale->status, [SaleStatus::Draft, SaleStatus::Cancelled], true)) {
                $accounting()->postSale($sale);
            }
        });

        // Les lignes sont créées après l'en-tête : la facture est comptabilisée dès que son total
        // est connu (changement de statut OU de total), l'idempotence évitant tout doublon.
        Sale::updated(function (Sale $sale) use ($accounting): void {
            if ($sale->status === SaleStatus::Cancelled) {
                if ($sale->wasChanged('status')) {
                    $accounting()->reverseSale($sale);
                }

                return;
            }

            if ($sale->status !== SaleStatus::Draft && ($sale->wasChanged('status') || $sale->wasChanged('total'))) {
                $accounting()->postSale($sale);
            }
        });

        // Dépenses : comptabilisées à la création (charge + TVA / fournisseur), extournées à l'annulation
        Expense::created(fn (Expense $expense) => $accounting()->postExpense($expense));

        Expense::updated(function (Expense $expense) use ($accounting): void {
            if ($expense->wasChanged('status') && $expense->status === ExpenseStatus::Cancelled) {
                $accounting()->reverseExpense($expense);
            }
        });

        // Paie : comptabilisée à la validation du bulletin
        Payroll::created(function (Payroll $payroll) use ($accounting): void {
            if (in_array($payroll->status, [PayrollStatus::Validated, PayrollStatus::Paid], true)) {
                $accounting()->postPayroll($payroll);
            }
        });

        Payroll::updated(function (Payroll $payroll) use ($accounting): void {
            if ($payroll->wasChanged('status') && in_array($payroll->status, [PayrollStatus::Validated, PayrollStatus::Paid], true)) {
                $accounting()->postPayroll($payroll);
            }
        });
    }
}
