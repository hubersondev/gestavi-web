<?php

namespace App\Filament\Farm\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Filament\Farm\Resources\Members\MemberResource;
use App\Models\OrganisationUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * Créer le User et l'OrganisationUser dans une transaction atomique.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
                'phone' => $data['user_phone'] ?? null,
                'password' => $data['user_password'],
                'type' => UserType::Farmer,
                'is_active' => true,
            ]);

            $memberData = [
                'organisation_id' => Filament::getTenant()->id,
                'user_id' => $user->id,
                'organisation_role_id' => $data['organisation_role_id'],
                'status' => $data['status'] ?? MemberStatus::Active->value,
            ];

            if (($data['status'] ?? null) === MemberStatus::Active->value) {
                $memberData['joined_at'] = now();
            }

            return OrganisationUser::create($memberData);
        });
    }

    /**
     * Après la création du membre, synchroniser les fermes assignées.
     * Les admins et propriétaires ont accès à toutes les fermes — aucune association nécessaire.
     */
    protected function afterCreate(): void
    {
        $farmIds = $this->data['farm_ids'] ?? [];

        if (! $this->record->canAccessAllFarms() && ! empty($farmIds)) {
            $this->record->farms()->sync($farmIds);
        }
    }
}
