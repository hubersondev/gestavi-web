<?php

namespace App\Console\Commands;

use App\Enums\PayrollStatus;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Services\PayrollService;
use Database\Seeders\BreedStandardSeeder;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\HealthProgramSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Database\Seeders\PayrollParameterSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Console\Command;

/**
 * À lancer après chaque déploiement : rejoue les référentiels partagés (idempotents)
 * puis remet chaque organisation au niveau du code (rôles système et leurs permissions,
 * phases de production et produits par défaut). Sans cela, une organisation créée avant
 * un module n'en reçoit ni les permissions ni les référentiels.
 */
class UpgradeTenants extends Command
{
    protected $signature = 'gestavi:tenants-upgrade
                            {--organisation= : Limiter à une organisation (toutes si absent)}
                            {--regenerate-draft-payrolls : Recalculer les bulletins en brouillon avec les règles de paie courantes}';

    protected $description = 'Remet à niveau les référentiels partagés et chaque organisation après un déploiement (permissions, plan comptable, paie, rôles système, phases, produits)';

    /** @var list<class-string> */
    private const array SHARED_SEEDERS = [
        PlatformRoleSeeder::class,
        OrganisationPermissionSeeder::class,
        CurrencySeeder::class,
        CountrySeeder::class,
        CitySeeder::class,
        ChickenTypeSeeder::class,
        PlanSeeder::class,
        HealthProgramSeeder::class,
        ChartAccountSeeder::class,
        BreedStandardSeeder::class,
        PayrollParameterSeeder::class,
    ];

    public function handle(PayrollService $payrollService): int
    {
        $this->info('Référentiels partagés…');

        foreach (self::SHARED_SEEDERS as $seeder) {
            $this->callSilently('db:seed', ['--class' => $seeder, '--force' => true]);
            $this->line('  ✓ '.class_basename($seeder));
        }

        $organisations = Organisation::query()
            ->when($this->option('organisation'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        $this->info('Organisations…');

        foreach ($organisations as $organisation) {
            $rolesBefore = $organisation->organisationRoles()->count();
            $permissionsBefore = $organisation->organisationRoles()->withCount('permissions')->get()->sum('permissions_count');
            $phasesBefore = $organisation->productionPhases()->count();
            $productsBefore = $organisation->products()->count();

            $organisation->createSystemRoles();
            $organisation->seedDefaultProductionPhases();
            $organisation->seedDefaultProducts();

            $permissionsAfter = $organisation->organisationRoles()->withCount('permissions')->get()->sum('permissions_count');

            $this->line(sprintf(
                '  ✓ %s : rôles %d→%d, permissions attribuées %d→%d, phases %d→%d, produits %d→%d',
                $organisation->name,
                $rolesBefore,
                $organisation->organisationRoles()->count(),
                $permissionsBefore,
                $permissionsAfter,
                $phasesBefore,
                $organisation->productionPhases()->count(),
                $productsBefore,
                $organisation->products()->count(),
            ));
        }

        if ($this->option('regenerate-draft-payrolls')) {
            $this->info('Bulletins en brouillon…');

            $payrolls = Payroll::query()
                ->where('status', PayrollStatus::Draft->value)
                ->whereIn('organisation_id', $organisations->pluck('id'))
                ->with(['contract', 'employee.activeContract'])
                ->get();

            $regenerated = 0;

            foreach ($payrolls as $payroll) {
                $contract = $payroll->contract ?? $payroll->employee?->activeContract;

                if (! $contract) {
                    $this->warn('  - '.$payroll->getReference().' : sans contrat, ignoré');

                    continue;
                }

                $netBefore = (float) $payroll->net_salary;
                $payrollService->generateItemsFromContract($payroll, $contract);
                $regenerated++;

                $this->line(sprintf('  ✓ %s : net %s → %s', $payroll->getReference(), number_format($netBefore, 0, ',', ' '), number_format((float) $payroll->fresh()->net_salary, 0, ',', ' ')));
            }

            $this->info("{$regenerated} bulletin(s) recalculé(s).");
        }

        return self::SUCCESS;
    }
}
