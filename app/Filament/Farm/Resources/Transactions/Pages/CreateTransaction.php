<?php

namespace App\Filament\Farm\Resources\Transactions\Pages;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Filament\Farm\Resources\Transactions\TransactionResource;
use App\Models\Account;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Account $account */
        $account = Account::findOrFail($data['account_id']);

        $type = TransactionType::from($data['type']);
        $category = TransactionCategory::from($data['category']);
        $amount = (float) $data['amount'];

        $transaction = $type === TransactionType::Credit
            ? $account->credit(
                amount: $amount,
                description: $data['description'],
                category: $category,
                referenceNumber: $data['reference_number'] ?? null,
            )
            : $account->debit(
                amount: $amount,
                description: $data['description'],
                category: $category,
                referenceNumber: $data['reference_number'] ?? null,
            );

        $transaction->update([
            'transaction_date' => $data['transaction_date'],
            'is_reconciled' => $data['is_reconciled'] ?? false,
            'reconciled_at' => ! empty($data['is_reconciled']) ? now() : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $transaction;
    }
}
