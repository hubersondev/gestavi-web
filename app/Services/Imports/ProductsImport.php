<?php

namespace App\Services\Imports;

use App\Enums\ProductKind;
use App\Models\Product;

class ProductsImport extends ImportDefinition
{
    public function key(): string
    {
        return 'products';
    }

    public function label(): string
    {
        return 'Produits vendus';
    }

    public function description(): string
    {
        return 'Catalogue des produits proposés à la vente (œufs, volailles, sous-produits).';
    }

    public function columns(): array
    {
        return [
            'nom' => ['Nom', true, ''],
            'code' => ['Code', false, 'Code court unique, vide = généré depuis le nom'],
            'type' => ['Type', true, 'plateau_oeufs, oeuf, poulet_vif, poulet_abattu, poule_reforme, fumier ou autre'],
            'unite' => ['Unité', true, 'plateau, sujet, kg, sac…'],
            'prix_unitaire' => ['Prix unitaire', false, 'Prix de vente par défaut en XOF'],
            'tva' => ['TVA %', false, 'Taux de TVA par défaut (0 si exonéré)'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['nom' => 'Plateau de 30 œufs', 'code' => 'OEUF-P30', 'type' => 'plateau_oeufs', 'unite' => 'plateau', 'prix_unitaire' => 3000, 'tva' => 0],
            ['nom' => 'Poulet de chair vif', 'code' => 'POUL-VIF', 'type' => 'poulet_vif', 'unite' => 'sujet', 'prix_unitaire' => 3500, 'tva' => 0],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $kind = $context->choice($values, 'type', $line, 'Type', [
            'plateau_oeufs' => ProductKind::EggsTray->value, 'plateau' => ProductKind::EggsTray->value, 'eggs_tray' => ProductKind::EggsTray->value,
            'oeuf' => ProductKind::EggsUnit->value, 'oeufs' => ProductKind::EggsUnit->value, 'eggs_unit' => ProductKind::EggsUnit->value,
            'poulet_vif' => ProductKind::LiveBird->value, 'vif' => ProductKind::LiveBird->value, 'live_bird' => ProductKind::LiveBird->value,
            'poulet_abattu' => ProductKind::DressedBird->value, 'abattu' => ProductKind::DressedBird->value, 'dressed_bird' => ProductKind::DressedBird->value,
            'poule_reforme' => ProductKind::CulledBird->value, 'reforme' => ProductKind::CulledBird->value, 'culled_bird' => ProductKind::CulledBird->value,
            'fumier' => ProductKind::Manure->value, 'manure' => ProductKind::Manure->value,
            'autre' => ProductKind::Other->value, 'other' => ProductKind::Other->value,
        ]);

        $name = $context->requiredString($values, 'nom', $line, 'Nom');
        $code = $context->string($values, 'code') ?: strtoupper(substr((string) SpreadsheetReader::normalizeHeader($name), 0, 20));

        if (Product::query()->where('organisation_id', $context->organisation->id)->where('code', $code)->exists()) {
            throw new ImportRowException($line, "le code produit « {$code} » existe déjà.");
        }

        Product::create([
            'organisation_id' => $context->organisation->id,
            'name' => $name,
            'code' => $code,
            'kind' => $kind,
            'unit' => $context->requiredString($values, 'unite', $line, 'Unité'),
            'default_unit_price' => $context->number($values, 'prix_unitaire', $line, 'Prix unitaire', 0.0),
            'default_tax_percent' => $context->number($values, 'tva', $line, 'TVA %', 0.0),
            'is_live_birds' => in_array($kind, [ProductKind::LiveBird->value, ProductKind::CulledBird->value], true),
            'is_active' => true,
        ]);
    }
}
