<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['platform_role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $platformRoleId = data_get($this->data, 'platform_role');

        if ($platformRoleId) {
            $this->record->platformRoles()->sync([$platformRoleId]);
        }
    }
}
