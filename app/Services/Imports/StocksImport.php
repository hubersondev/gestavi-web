<?php

namespace App\Services\Imports;

use App\Models\Stock;

class StocksImport extends ImportDefinition
{
    public function key(): string
    {
        return 'stocks';
    }

    public function label(): string
    {
        return 'Stocks';
    }

    public function description(): string
    {
        return 'Articles en stock avec leur quantité de départ. La catégorie est créée si elle n\'existe pas.';
    }

    public function columns(): array
    {
        return [
            'nom' => ['Nom', true, ''],
            'categorie' => ['Catégorie', true, 'Nom de la catégorie (ex. Aliment, Médicaments, Litière)'],
            'type_categorie' => ['Type de catégorie', false, 'aliment, medicament, materiel, emballage ou autre, utilisé si la catégorie est créée'],
            'unite' => ['Unité', true, 'kg, sac, boîte, litre…'],
            'quantite' => ['Quantité', false, 'Quantité de départ'],
            'quantite_min' => ['Quantité minimale', false, 'Seuil d\'alerte de stock bas'],
            'prix_unitaire' => ['Prix unitaire', false, 'Coût unitaire en XOF'],
            'ferme' => ['Ferme', false, 'Nom ou code de la ferme, obligatoire si plusieurs fermes'],
            'date_peremption' => ['Date de péremption', false, 'JJ/MM/AAAA'],
            'emplacement' => ['Emplacement', false, ''],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['nom' => 'Aliment démarrage', 'categorie' => 'Aliment', 'type_categorie' => 'aliment', 'unite' => 'sac', 'quantite' => 40, 'quantite_min' => 10, 'prix_unitaire' => 18500, 'ferme' => '', 'date_peremption' => '', 'emplacement' => 'Magasin 1'],
            ['nom' => 'Vaccin Newcastle', 'categorie' => 'Médicaments', 'type_categorie' => 'medicament', 'unite' => 'flacon', 'quantite' => 12, 'quantite_min' => 4, 'prix_unitaire' => 4500, 'ferme' => '', 'date_peremption' => '30/06/2027', 'emplacement' => 'Réfrigérateur'],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $type = $context->choice($values, 'type_categorie', $line, 'Type de catégorie', [
            'aliment' => 'feed', 'feed' => 'feed',
            'medicament' => 'medicine', 'medicaments' => 'medicine', 'medicine' => 'medicine',
            'materiel' => 'material', 'material' => 'material',
            'emballage' => 'packaging', 'packaging' => 'packaging',
            'autre' => 'other', 'other' => 'other',
        ], 'other');

        $category = $context->stockCategoryOrCreate($context->requiredString($values, 'categorie', $line, 'Catégorie'), $type);
        $quantity = $context->number($values, 'quantite', $line, 'Quantité', 0.0);

        if ($quantity < 0) {
            throw new ImportRowException($line, 'la quantité ne peut pas être négative.');
        }

        Stock::create([
            'organisation_id' => $context->organisation->id,
            'farm_id' => $context->farm($values, $line)?->id,
            'stock_category_id' => $category->id,
            'name' => $context->requiredString($values, 'nom', $line, 'Nom'),
            'unit' => $context->requiredString($values, 'unite', $line, 'Unité'),
            'quantity' => $quantity,
            'min_quantity' => $context->number($values, 'quantite_min', $line, 'Quantité minimale', 0.0),
            'unit_price' => $context->number($values, 'prix_unitaire', $line, 'Prix unitaire', 0.0),
            'expiry_date' => $context->date($values, 'date_peremption', $line, 'Date de péremption')?->toDateString(),
            'location' => $context->string($values, 'emplacement'),
            'is_active' => true,
        ]);
    }
}
