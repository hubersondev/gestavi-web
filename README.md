# GESTAVI — Gestion de fermes avicoles

Plateforme multi-organisations et multi-fermes de pilotage d'élevages avicoles (Côte d'Ivoire) :
bandes et suivi quotidien, prophylaxie, stocks et achats, ventes, trésorerie, RH et paie, rapports.

## Stack

| Composant | Version |
|---|---|
| PHP | 8.3 |
| Laravel | 12 |
| Filament | 5 (panels `/admin` et `/farm/{organisation}`) |
| Livewire | 4 |
| Base de données | PostgreSQL 16+ (contraintes CHECK, `jsonb`) |
| Tests | Pest 4 |

## Installation locale

```bash
git clone <dépôt> gestavi && cd gestavi
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Créer deux bases PostgreSQL (`db_gestavi` pour le développement, `db_gestavi_test` pour les tests),
renseigner `DB_*` dans `.env`, puis :

```bash
php artisan migrate --seed
php artisan serve
```

Comptes de démonstration (mot de passe `password`, à changer hors développement) :
`superadmin@gestavi.com` (panel Admin), `jean@ferme-demo.ci` (propriétaire de l'organisation de démo),
`ama@ferme-demo.ci` (employé).

## Tests et qualité

```bash
php artisan test --compact          # suite complète sur PostgreSQL (db_gestavi_test)
php artisan test --compact tests/Feature/FlockEffectifTest.php
vendor/bin/pint                     # formatage (obligatoire avant commit)
```

`phpunit.xml` lit `DB_PASSWORD` depuis `.env`. Pour lancer plusieurs suites en parallèle,
préfixer par `DB_DATABASE=<autre base>`.

Le schéma est consolidé dans `database/schema/pgsql-schema.sql` (les migrations antérieures au
19/09/2026 ont été fusionnées) : `migrate:fresh` et la suite de tests le chargent avec `psql`, qui doit
donc être dans le `PATH` (`C:\Program Files\PostgreSQL\17\bin` sous Windows). Les nouvelles évolutions
de schéma restent des migrations classiques dans `database/migrations/`. Régénérer le dump après une
série de migrations : `php artisan schema:dump --prune` (nécessite `pg_dump`).

PostgreSQL est la seule base supportée : les requêtes de rapport utilisent `DISTINCT ON`, `FILTER` et `to_char`.

## Tâches planifiées et files

```bash
php artisan schedule:work   # alertes (péremption, vaccinations, relevés manquants, factures échues, stock bas), sauvegardes
php artisan queue:work      # exports PDF/Excel, notifications
```

Sauvegardes : `spatie/laravel-backup` (`php artisan backup:run --only-db`), disque `local` par défaut ;
`pg_dump` doit être dans le `PATH` ou renseigné via `DB_DUMP_BINARY_PATH`.

Supervision : `sentry/sentry-laravel`, activé dès que `SENTRY_LARAVEL_DSN` est renseigné.

## Modules livrés par la refonte (septembre 2026)

- **Discriminant unique** : chaque table de faits porte `organisation_id` et `farm_id` (trait `BelongsToOrganisation`,
  clé étrangère composite vers `farms`) ; scopes Filament directs ; règles serveur sur les clés étrangères.
- **Coût de revient par bande** (`FlockCostService`, page « Coût de revient ») et **tableau de bord groupe**
  sur agrégats quotidiens (`farm_daily_metrics`, commande `gestavi:aggregate-farm-metrics`).
- **Chaîne stock ↔ élevage ↔ ventes** : catalogue produits, déstockage de l'aliment depuis le relevé,
  des médicaments à l'administration, abattages, livraisons avec bon PDF.
- **Comptabilité SYSCOHADA minimale** : plan de comptes, écritures en partie double générées par écouteurs,
  exercices et clôture, journal / grand livre / balance / résultat, export « journal Sage »,
  `gestavi:accounting-rebuild` pour initialiser une base existante.
- **Ledger intangible** : triggers PostgreSQL interdisant toute modification ou suppression des écritures,
  de leurs lignes et des transactions de trésorerie (seul le rapprochement bancaire reste modifiable) ;
  chaîne de hachage SHA-256 par organisation (`previous_hash` → `hash`) ; ancrages de la tête de chaîne à
  la clôture d'exercice, à la reconstruction et chaque nuit (`gestavi:ledger-anchor`), copiés hors base sur
  le disque `LEDGER_ANCHOR_DISK` ; `gestavi:ledger-verify` rejoue la chaîne et désigne la première écriture
  altérée. La reconstruction (`gestavi:accounting-rebuild`) est la seule opération qui désarme les triggers,
  via `SET LOCAL gestavi.ledger_unlock`, et elle laisse un ancrage daté.
- **Saisie terrain hors-ligne** : page `/farm/{organisation}/terrain` (PWA, IndexedDB, synchronisation idempotente).
- **SMS et mobile money** : pilotes SMS (log, Africa's Talking, Orange), webhooks signés Orange/MTN/Moov/Wave,
  rapprochement automatique et manuel des encaissements (`config/sms.php`, `config/mobilemoney.php`).

Les valeurs légales (paie, plan comptable, imputations) sont des défauts documentés à valider par un expert-comptable ;
les formats de webhooks opérateurs sont des contrats à confirmer avec de vrais identifiants.

## Architecture

- **Tenant Filament** = `Organisation` ; **ferme** = second niveau de cloisonnement (`CurrentFarm`, trait `ScopesToMemberFarms`).
  Un membre de rôle `manager`/`employee` ne voit que ses fermes affectées ; `owner`/`admin` voient tout.
- **Rôles et permissions** en base (`organisation_roles`, `organisation_permissions`), trait `ChecksOrganisationPermissions`
  sur chaque ressource, `RequiresOrganisationPermission` sur les rapports et widgets.
- **Effectif d'une bande** dérivé des relevés (mortalité, réformes) et des ventes de sujets vivants ; jamais saisi à la main.
- **Journaux intangibles** : transactions, paiements, mouvements de stock ne s'éditent ni ne se suppriment (contrepassation).
- **Piste d'audit** : `spatie/laravel-activitylog` sur les modèles sensibles (trait `LogsAuditTrail`).
- **Numérotation** continue et verrouillée (`NumberSequence`, table `sequences`).
- **Authentification à deux facteurs** (application TOTP) exigée des utilisateurs plateforme et des propriétaires/administrateurs.

Le rapport d'audit complet et le plan d'action sont dans `AUDIT.md`.

## Conventions

- Code, commentaires, libellés et messages en français ; PHPDoc plutôt que commentaires inline.
- Filament 5 : `Schema` / `->components()`, `Filament\Actions\Action`, `Get` depuis `Filament\Schemas\Components\Utilities`.
- Toute évolution est couverte par un test Pest (Feature de préférence) ; `vendor/bin/pint` avant commit.
- Ne pas ajouter de CRUD brut sur une table de journal ; passer par les services (`FlockService`, `SaleService`, `StockService`, `PayrollService`).
