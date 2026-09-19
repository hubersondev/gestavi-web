<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute Moov Money (4e opérateur ivoirien) aux contraintes CHECK des modes
 * de paiement, en les générant depuis l'enum PaymentMethod (source unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $list = $this->enumList(PaymentMethod::class);

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ({$list}))");

        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_payment_method_check');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_payment_method_check CHECK (payment_method IS NULL OR payment_method IN ({$list}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $list = "'cash','orange_money','mtn_momo','wave','bank_transfer','cheque','credit'";

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ({$list}))");

        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_payment_method_check');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_payment_method_check CHECK (payment_method IS NULL OR payment_method IN ({$list}))");
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private function enumList(string $enum): string
    {
        return collect($enum::cases())
            ->map(fn (\BackedEnum $case): string => "'".$case->value."'")
            ->implode(',');
    }
};
