<?php

namespace App\Services\Imports;

use App\Models\Supplier;

class SuppliersImport extends ImportDefinition
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function label(): string
    {
        return 'Fournisseurs';
    }

    public function description(): string
    {
        return 'Un fournisseur par ligne. Sans ferme, le fournisseur est partagé par toute l\'organisation.';
    }

    public function columns(): array
    {
        return [
            'nom' => ['Nom', true, 'Raison sociale'],
            'contact' => ['Contact', false, 'Nom de l\'interlocuteur'],
            'telephone' => ['Téléphone', false, ''],
            'email' => ['Email', false, ''],
            'adresse' => ['Adresse', false, ''],
            'numero_contribuable' => ['Numéro contribuable', false, ''],
            'ferme' => ['Ferme', false, 'Nom ou code de la ferme, vide = toute l\'organisation'],
            'notes' => ['Notes', false, ''],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['nom' => 'FACI', 'contact' => 'M. Diabaté', 'telephone' => '2722000000', 'email' => '', 'adresse' => 'Zone industrielle, Yopougon', 'numero_contribuable' => '', 'ferme' => '', 'notes' => 'Aliment volaille'],
            ['nom' => 'Pharmacie vétérinaire du Nord', 'contact' => '', 'telephone' => '0505050505', 'email' => '', 'adresse' => 'Korhogo', 'numero_contribuable' => '', 'ferme' => '', 'notes' => ''],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        Supplier::create([
            'organisation_id' => $context->organisation->id,
            'farm_id' => $context->farm($values, $line, required: false)?->id,
            'name' => $context->requiredString($values, 'nom', $line, 'Nom'),
            'contact_name' => $context->string($values, 'contact'),
            'phone' => $context->string($values, 'telephone'),
            'email' => $context->string($values, 'email'),
            'address' => $context->string($values, 'adresse'),
            'tax_id' => $context->string($values, 'numero_contribuable'),
            'notes' => $context->string($values, 'notes'),
            'is_active' => true,
        ]);
    }
}
