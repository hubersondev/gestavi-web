<?php

namespace App\Services\Imports;

use App\Enums\CustomerType;
use App\Models\Customer;

class CustomersImport extends ImportDefinition
{
    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'Clients';
    }

    public function description(): string
    {
        return 'Un client par ligne. Le code client est généré automatiquement.';
    }

    public function columns(): array
    {
        return [
            'nom' => ['Nom', true, 'Nom ou raison sociale'],
            'type' => ['Type', false, 'particulier, revendeur, restaurant, hotel, supermarche ou autre (défaut : particulier)'],
            'telephone' => ['Téléphone', false, ''],
            'telephone_2' => ['Téléphone 2', false, ''],
            'email' => ['Email', false, ''],
            'adresse' => ['Adresse', false, ''],
            'plafond_credit' => ['Plafond crédit', false, 'Montant maximal d\'impayés autorisé, 0 = pas de crédit'],
            'ferme' => ['Ferme', false, 'Nom ou code de la ferme rattachée, vide = toute l\'organisation'],
            'notes' => ['Notes', false, ''],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['nom' => 'Maquis Chez Tantie', 'type' => 'restaurant', 'telephone' => '0708091011', 'telephone_2' => '', 'email' => '', 'adresse' => 'Yopougon, Abidjan', 'plafond_credit' => 150000, 'ferme' => '', 'notes' => 'Livraison le mardi'],
            ['nom' => 'Koffi Aya', 'type' => 'particulier', 'telephone' => '0102030405', 'telephone_2' => '', 'email' => '', 'adresse' => '', 'plafond_credit' => 0, 'ferme' => '', 'notes' => ''],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $type = $context->choice($values, 'type', $line, 'Type', array_combine(
            array_map(fn (CustomerType $case): string => $case->value, CustomerType::cases()),
            array_map(fn (CustomerType $case): string => $case->value, CustomerType::cases()),
        ) + ['hôtel' => 'hotel', 'supermarché' => 'supermarche'], CustomerType::Particulier->value);

        Customer::create([
            'organisation_id' => $context->organisation->id,
            'farm_id' => $context->farm($values, $line, required: false)?->id,
            'name' => $context->requiredString($values, 'nom', $line, 'Nom'),
            'type' => $type,
            'phone' => $context->string($values, 'telephone'),
            'phone_secondary' => $context->string($values, 'telephone_2'),
            'email' => $context->string($values, 'email'),
            'address' => $context->string($values, 'adresse'),
            'credit_limit' => $context->number($values, 'plafond_credit', $line, 'Plafond crédit', 0.0),
            'notes' => $context->string($values, 'notes'),
            'is_active' => true,
        ]);
    }
}
