<?php

namespace Database\Seeders;

use App\Models\AccountingMapping;
use App\Models\ChartAccount;
use App\Services\AccountingMapper;
use Illuminate\Database\Seeder;

/**
 * Plan comptable de référence SYSCOHADA (organisation NULL) et imputations par défaut.
 * Idempotent.
 */
class ChartAccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ChartAccount::REFERENCE_PLAN as $code => [$label, $type]) {
            ChartAccount::query()->updateOrCreate(
                ['organisation_id' => null, 'code' => (string) $code],
                [
                    'label' => $label,
                    'class' => ChartAccount::classOf((string) $code),
                    'type' => $type,
                    'is_active' => true,
                    'source' => ChartAccount::SOURCE,
                ],
            );
        }

        foreach (AccountingMapper::DEFAULTS as $key => $code) {
            AccountingMapping::query()->updateOrCreate(
                ['organisation_id' => null, 'mapping_key' => $key],
                ['chart_account_code' => $code, 'description' => 'Imputation par défaut GESTAVI'],
            );
        }
    }
}
