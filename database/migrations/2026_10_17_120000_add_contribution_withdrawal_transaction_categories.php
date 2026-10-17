<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Réapprovisionnement des comptes : catégories de transaction « contribution » (apport de fonds
 * de l'exploitant ou des associés) et « withdrawal » (prélèvement de l'exploitant), imputées au
 * compte 104 « Compte de l'exploitant ». La contrainte CHECK des catégories est élargie.
 */
return new class extends Migration
{
    private const string CATEGORIES_BEFORE = "'opening', 'sale', 'payment', 'expense', 'transfer_in', 'transfer_out', 'adjustment', 'payroll', 'refund', 'theft', 'other'";

    private const string CATEGORIES_AFTER = "'opening', 'sale', 'payment', 'expense', 'transfer_in', 'transfer_out', 'adjustment', 'payroll', 'refund', 'theft', 'contribution', 'withdrawal', 'other'";

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_category_check');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_category_check CHECK (category IN ('.self::CATEGORIES_AFTER.'))');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::table('transactions')->whereIn('category', ['contribution', 'withdrawal'])->update(['category' => 'other']);
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_category_check');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_category_check CHECK (category IN ('.self::CATEGORIES_BEFORE.'))');
    }
};
