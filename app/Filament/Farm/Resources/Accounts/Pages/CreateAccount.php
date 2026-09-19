<?php

namespace App\Filament\Farm\Resources\Accounts\Pages;

use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $data['organisation_id'] = $tenant?->getKey();

        /** @var Account $account */
        $account = app(AccountService::class)->createAccount($data);

        return $account;
    }
}
