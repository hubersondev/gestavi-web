<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Injecter le rôle depuis la pivot dans le formulaire
        $data['platform_role'] = $this->record->platformRoles()->first()?->id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['platform_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $platformRoleId = data_get($this->data, 'platform_role');

        if ($platformRoleId) {
            $this->record->platformRoles()->sync([$platformRoleId]);
        } else {
            $this->record->platformRoles()->detach();
        }
    }
}
