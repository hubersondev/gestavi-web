<?php

namespace Database\Seeders;

use App\Models\PayrollParameter;
use Illuminate\Database\Seeder;

/**
 * Paramétrage de paie légal par défaut (organisation NULL), idempotent.
 */
class PayrollParameterSeeder extends Seeder
{
    public function run(): void
    {
        $data = PayrollParameter::LEGAL_DEFAULTS_2024;
        $brackets = $data['brackets'];
        unset($data['brackets']);

        $parameter = PayrollParameter::query()->updateOrCreate(
            ['organisation_id' => null, 'effective_from' => $data['effective_from']],
            $data,
        );

        $parameter->taxBrackets()->delete();

        foreach ($brackets as $order => $bracket) {
            $parameter->taxBrackets()->create(array_merge($bracket, ['order' => $order]));
        }
    }
}
