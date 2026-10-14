<?php

namespace App\Services;

use App\Exceptions\ExpenseException;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Enregistre une dépense et, si `pay_now` est vrai, effectue immédiatement le paiement depuis le compte.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordExpense(array $data, bool $payNow = false, ?\DateTimeInterface $paidAt = null): Expense
    {
        return DB::transaction(function () use ($data, $payNow, $paidAt): Expense {
            $expense = Expense::create($data);

            if ($payNow && $expense->account_id && (float) $expense->amount > 0) {
                $expense->recordPayment((float) $expense->amount, $expense->account_id, $paidAt);
            }

            return $expense->fresh();
        });
    }

    /**
     * Enregistre un paiement sur une dépense existante.
     */
    public function recordPayment(Expense $expense, float $amount, ?int $accountId = null): Expense
    {
        if ($amount <= 0) {
            throw ExpenseException::invalidAmount();
        }

        $expense->recordPayment($amount, $accountId);

        return $expense->fresh();
    }

    /**
     * Annule une dépense et contrepasse les écritures comptables déjà effectuées.
     */
    public function cancelExpense(Expense $expense): Expense
    {
        $expense->cancel();

        return $expense->fresh();
    }
}
