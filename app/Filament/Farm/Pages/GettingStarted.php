<?php

namespace App\Filament\Farm\Pages;

use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Filament\Farm\Resources\Buildings\BuildingResource;
use App\Filament\Farm\Resources\Customers\CustomerResource;
use App\Filament\Farm\Resources\Employees\EmployeeResource;
use App\Filament\Farm\Resources\Farms\FarmResource;
use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Filament\Farm\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Farm\Resources\Members\MemberResource;
use App\Filament\Farm\Resources\Stocks\StockResource;
use App\Models\Account;
use App\Models\Building;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\FiscalYear;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\AccountingService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;

/**
 * Guide de démarrage d'une organisation : les étapes dans l'ordre où elles doivent être faites,
 * avec leur état et un lien vers l'écran concerné. Le badge du menu compte les étapes restantes.
 */
class GettingStarted extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Démarrage';

    protected static ?string $title = 'Guide de démarrage';

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'demarrage';

    protected string $view = 'filament.farm.pages.getting-started';

    public static function getNavigationBadge(): ?string
    {
        $remaining = collect(static::stepsFor(Filament::getTenant()))
            ->reject(fn (array $step): bool => $step['done'] || $step['optional'])
            ->count();

        return $remaining > 0 ? (string) $remaining : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return list<array{key: string, title: string, description: string, done: bool, optional: bool, url: ?string, action: string}>
     */
    public function getSteps(): array
    {
        return static::stepsFor(Filament::getTenant());
    }

    /**
     * @return list<array{key: string, title: string, description: string, done: bool, optional: bool, url: ?string, action: string}>
     */
    public static function stepsFor(?Organisation $organisation): array
    {
        if (! $organisation) {
            return [];
        }

        $id = $organisation->getKey();
        $farms = Farm::query()->where('organisation_id', $id)->count();

        return [
            static::step('farms', 'Créer vos fermes', 'Chaque site d\'élevage est une ferme. Les membres peuvent ensuite être affectés à une ou plusieurs fermes.',
                $farms > 0, FarmResource::class, 'create', 'Ajouter une ferme'),
            static::step('buildings', 'Décrire les bâtiments', 'Les bandes sont logées dans un bâtiment d\'une ferme.',
                Building::query()->whereHas('farm', fn ($q) => $q->where('organisation_id', $id))->exists(), BuildingResource::class, 'create', 'Ajouter un bâtiment'),
            static::step('fiscal_years', 'Ouvrir les exercices comptables', 'Un exercice par année civile, en commençant par l\'année la plus ancienne à saisir. Le sélecteur en haut de page permet ensuite de travailler année par année.',
                FiscalYear::query()->where('organisation_id', $id)->exists(), FiscalYearResource::class, 'create', 'Créer un exercice'),
            static::step('accounts', 'Créer les comptes de trésorerie', 'Caisse, banque, mobile money, avec leur solde et la date de ce solde (premier jour de l\'exercice repris).',
                Account::query()->where('organisation_id', $id)->exists(), AccountResource::class, 'create', 'Ajouter un compte'),
            static::step('opening_balance', 'Saisir le bilan d\'ouverture', 'Seulement si l\'entreprise existait avant : capital, immobilisations, emprunts, créances et dettes au premier jour de l\'exercice repris.',
                app(AccountingService::class)->openingBalanceEntry((int) $id) !== null, JournalEntryResource::class, 'index', 'Ouvrir les écritures', optional: true),
            static::step('members', 'Inviter les membres', 'Gérants et employés avec leur rôle. Chaque rôle détermine ce qu\'ils voient et ce qu\'ils peuvent faire.',
                OrganisationUser::query()->where('organisation_id', $id)->count() > 1, MemberResource::class, 'create', 'Ajouter un membre', optional: true),
            static::step('customers', 'Enregistrer clients et fournisseurs', 'À la main ou par import Excel depuis Paramètres → Import de données, modèle fourni.',
                Customer::query()->where('organisation_id', $id)->exists() && Supplier::query()->where('organisation_id', $id)->exists(), CustomerResource::class, 'index', 'Ouvrir les clients'),
            static::step('stocks', 'Initialiser les stocks', 'Aliments, médicaments, litière avec leurs quantités de départ. Import Excel possible.',
                Stock::query()->where('organisation_id', $id)->exists(), StockResource::class, 'index', 'Ouvrir les stocks', optional: true),
            static::step('employees', 'Enregistrer les employés', 'Nécessaire pour la paie : fiche, contrat, puis bulletins mensuels. Import Excel possible.',
                Employee::query()->where('organisation_id', $id)->exists(), EmployeeResource::class, 'index', 'Ouvrir les employés', optional: true),
            static::step('flocks', 'Lancer la première bande', 'La bande porte le suivi quotidien, la prophylaxie, les coûts et les ventes de sujets.',
                Flock::query()->whereHas('building.farm', fn ($q) => $q->where('organisation_id', $id))->exists(), FlockResource::class, 'create', 'Créer une bande'),
        ];
    }

    /**
     * @param  class-string<resource>  $resource
     * @return array{key: string, title: string, description: string, done: bool, optional: bool, url: ?string, action: string}
     */
    private static function step(string $key, string $title, string $description, bool $done, string $resource, string $page, string $action, bool $optional = false): array
    {
        $url = null;

        if ($resource::canViewAny() && ($page !== 'create' || $resource::canCreate())) {
            $url = $resource::getUrl($page);
        }

        return compact('key', 'title', 'description', 'done', 'optional', 'url', 'action');
    }
}
