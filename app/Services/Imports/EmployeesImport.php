<?php

namespace App\Services\Imports;

use App\Services\EmployeeService;

class EmployeesImport extends ImportDefinition
{
    public function key(): string
    {
        return 'employees';
    }

    public function label(): string
    {
        return 'Employés';
    }

    public function description(): string
    {
        return 'Une fiche employé par ligne. Les contrats se créent ensuite dans l\'application.';
    }

    public function columns(): array
    {
        return [
            'matricule' => ['Matricule', false, 'Vide = généré automatiquement'],
            'prenom' => ['Prénom', true, ''],
            'nom' => ['Nom', true, ''],
            'genre' => ['Genre', false, 'homme, femme ou autre'],
            'date_naissance' => ['Date de naissance', false, 'JJ/MM/AAAA'],
            'telephone' => ['Téléphone', false, ''],
            'email' => ['Email', false, ''],
            'adresse' => ['Adresse', false, ''],
            'service' => ['Service', false, 'Ex. Élevage, Administration'],
            'date_embauche' => ['Date d\'embauche', true, 'JJ/MM/AAAA'],
            'numero_cnps' => ['Numéro CNPS', false, ''],
            'parts_fiscales' => ['Parts fiscales', false, 'De 1 à 5 (défaut : 1)'],
            'mode_paiement' => ['Mode de paiement', false, 'especes, virement ou mobile_money (défaut : especes)'],
            'ferme' => ['Ferme', false, 'Nom ou code de la ferme d\'affectation'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['matricule' => '', 'prenom' => 'Ama', 'nom' => 'Koné', 'genre' => 'femme', 'date_naissance' => '12/03/1992', 'telephone' => '0102030405', 'email' => '', 'adresse' => 'Anyama', 'service' => 'Élevage', 'date_embauche' => '01/02/2024', 'numero_cnps' => '', 'parts_fiscales' => 1, 'mode_paiement' => 'mobile_money', 'ferme' => ''],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $gender = $context->choice($values, 'genre', $line, 'Genre', [
            'homme' => 'male', 'masculin' => 'male', 'male' => 'male', 'm' => 'male',
            'femme' => 'female', 'feminin' => 'female', 'female' => 'female', 'f' => 'female',
            'autre' => 'other', 'other' => 'other',
        ]);

        $paymentMethod = $context->choice($values, 'mode_paiement', $line, 'Mode de paiement', [
            'especes' => 'cash', 'cash' => 'cash',
            'virement' => 'bank_transfer', 'banque' => 'bank_transfer', 'bank_transfer' => 'bank_transfer',
            'mobile_money' => 'mobile_money', 'mobile' => 'mobile_money',
        ], 'cash');

        $fiscalParts = $context->number($values, 'parts_fiscales', $line, 'Parts fiscales', 1.0);

        if ($fiscalParts < 1 || $fiscalParts > 5) {
            throw new ImportRowException($line, 'les parts fiscales vont de 1 à 5.');
        }

        app(EmployeeService::class)->createEmployee([
            'farm_id' => $context->farm($values, $line, required: false)?->id,
            'employee_number' => $context->string($values, 'matricule') ?: null,
            'first_name' => $context->requiredString($values, 'prenom', $line, 'Prénom'),
            'last_name' => $context->requiredString($values, 'nom', $line, 'Nom'),
            'gender' => $gender,
            'birth_date' => $context->date($values, 'date_naissance', $line, 'Date de naissance')?->toDateString(),
            'phone' => $context->string($values, 'telephone'),
            'email' => $context->string($values, 'email'),
            'address' => $context->string($values, 'adresse'),
            'department' => $context->string($values, 'service'),
            'hire_date' => $context->requiredDate($values, 'date_embauche', $line, 'Date d\'embauche')->toDateString(),
            'cnps_number' => $context->string($values, 'numero_cnps'),
            'fiscal_parts' => $fiscalParts,
            'payment_method' => $paymentMethod,
            'status' => 'active',
        ], $context->organisation);
    }
}
