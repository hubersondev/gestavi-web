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

`tests/Feature/PanelSmokeTest.php` ouvre chaque page des deux panels par une vraie requête HTTP (panel
démarré, scopes de tenant actifs) pour le propriétaire, un employé restreint et le super-administrateur :
à lancer après toute modification d'une ressource, d'un rapport ou d'un scope.

`phpunit.xml` lit `DB_PASSWORD` depuis `.env`. Pour lancer plusieurs suites en parallèle,
préfixer par `DB_DATABASE=<autre base>`.

Le schéma est consolidé dans `database/schema/pgsql-schema.sql` (les migrations antérieures au
19/09/2026 ont été fusionnées) : `migrate:fresh` et la suite de tests le chargent avec `psql`, qui doit
donc être dans le `PATH` (`C:\Program Files\PostgreSQL\17\bin` sous Windows). Les nouvelles évolutions
de schéma restent des migrations classiques dans `database/migrations/`. Régénérer le dump après une
série de migrations : `php artisan schema:dump --prune` (nécessite `pg_dump`).

PostgreSQL est la seule base supportée : les requêtes de rapport utilisent `DISTINCT ON`, `FILTER` et `to_char`.

## Environnement de recette et de production

Trois processus doivent tourner en permanence, plus un cron :

```bash
php artisan serve                 # ou php-fpm derrière nginx/Caddy avec APP_URL en HTTPS
php artisan queue:work --tries=3  # worker de file (Supervisor ou service systemd, redémarré à chaque déploiement)
* * * * * cd /var/www/gestavi && php artisan schedule:run >> /dev/null 2>&1   # cron du planificateur
```

Réglages attendus hors développement : `APP_ENV=production` (ou `staging`), `APP_DEBUG=false`, `APP_URL=https://…`,
`QUEUE_CONNECTION=database` ou `redis`, `MAIL_MAILER` réel, `SENTRY_LARAVEL_DSN` renseigné, un disque de
sauvegarde hors serveur dans `config/backup.php` et `LEDGER_ANCHOR_DISK` sur un stockage distant.

`php artisan gestavi:doctor` contrôle tout cela en une fois (base, migrations en attente, file, battement de
cœur du planificateur, âge de la dernière sauvegarde, `pg_dump`, intégrité et ancrage du ledger, Sentry,
courriel, SMS, secrets mobile money, 2FA des comptes à privilèges, organisations à niveau, dossiers
inscriptibles) et sort en erreur dès qu'un prérequis manque. À lancer après l'installation, puis chaque jour
par la supervision.

Recette d'une semaine avant ouverture : une organisation réelle, ses fermes et ses membres ; relevés
quotidiens saisis depuis la page terrain ; au moins une vente payée par mobile money de test, une dépense,
un bulletin validé puis payé ; `gestavi:ledger-verify` et `backup:monitor` verts chaque matin ;
`gestavi:doctor` sans échec le dernier jour.

## Après chaque déploiement

```bash
php artisan migrate --force
php artisan gestavi:tenants-upgrade            # référentiels partagés + rôles, permissions, phases, produits de chaque organisation
php artisan gestavi:tenants-upgrade --regenerate-draft-payrolls   # si les règles de paie ont changé
php artisan gestavi:accounting-rebuild         # si des imputations ou écritures générées ont changé
php artisan gestavi:ledger-verify
```

Une organisation créée avant un module n'en reçoit ni les permissions ni les référentiels tant que
`gestavi:tenants-upgrade` n'a pas tourné : les rôles non propriétaires voient alors un 403 sur ce module.

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
  exercices et clôture (classes 6 et 7 soldées vers le 131, puis à-nouveaux des comptes de bilan au journal AN
  le lendemain de la fin d'exercice), journal / grand livre / balance / résultat, export « journal Sage »,
  `gestavi:accounting-rebuild` pour initialiser une base existante (les écritures de clôture et d'à-nouveaux
  sont conservées). Un exercice créé ou redaté après coup récupère les écritures de sa période ; ce rattachement
  est la seule colonne d'une écriture que le ledger laisse modifier.
- **Reprise d'un exercice passé** (client qui démarre en cours d'année ou saisit l'année précédente) : les numéros
  de pièces (factures, avoirs, dépenses, devis, bons, transferts) suivent l'année de la date du document ; le solde
  initial d'un compte de trésorerie est daté (champ « Date du solde initial », transaction d'ouverture référencée
  `OUVERTURE` et jamais recomptée) ; le **bilan d'ouverture** (action sur la liste des écritures, permission
  `fiscal_years.opening_balance`) reprend une seule fois capital, immobilisations, emprunts, créances et dettes au
  journal AN, classes 1 à 4 uniquement.
- **Ledger intangible** : triggers PostgreSQL interdisant toute modification ou suppression des écritures,
  de leurs lignes et des transactions de trésorerie (seul le rapprochement bancaire reste modifiable) ;
  chaîne de hachage SHA-256 par organisation (`previous_hash` → `hash`) ; ancrages de la tête de chaîne à
  la clôture d'exercice, à la reconstruction et chaque nuit (`gestavi:ledger-anchor`), copiés hors base sur
  le disque `LEDGER_ANCHOR_DISK` ; `gestavi:ledger-verify` rejoue la chaîne et désigne la première écriture
  altérée. La reconstruction (`gestavi:accounting-rebuild`) est la seule opération qui désarme les triggers,
  via `SET LOCAL gestavi.ledger_unlock`, et elle laisse un ancrage daté.
- **Paie alignée sur le bulletin ivoirien** (recoupée sur un bulletin réel de juillet 2026) : ITS assis
  directement sur le brut imposable (réforme 2024), prime de transport exonérée jusqu'à 30 000 FCFA,
  CMU 500 + 500, FDFP 0,4 % + 0,6 %, salaire catégoriel / sursalaire et catégorie professionnelle sur
  le contrat, N° CNPS employeur, instantané des charges patronales figé sur chaque bulletin, écriture
  de paie complétée (433 CMU, 6413/4423 FDFP). Le taux accident du travail (2 à 5 % selon le secteur)
  se règle dans le paramétrage de paie de l'organisation.
- **Exercice actif** (sélecteur dans la barre du haut, service `CurrentFiscalYear`) : filtre d'affichage des listes
  datées (ventes, avoirs, devis, livraisons, dépenses, bons, transactions, transferts, paiements, paie, stocks,
  écritures, relevés, pointages, abattages, journal de ferme), période par défaut des rapports, date proposée dans
  les formulaires avec avertissement non bloquant hors exercice. Jamais une règle de gestion : les services
  (clôture, à-nouveaux, reconstruction, API, synchronisation terrain) l'ignorent, et « Tous les exercices » lève le filtre.
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
- Le panel Farm sert le thème `resources/css/filament/farm/theme.css` (compilé par `npm run build`, à relancer après
  chaque `git pull`) : ses `@source` couvrent `app/Filament/Farm` et `resources/views/filament/farm`, ce qui rend
  disponibles les classes Tailwind des vues personnalisées (rapports, tableau de bord groupe). Une vue placée
  ailleurs n'en bénéficie pas.
- Toute évolution est couverte par un test Pest (Feature de préférence) ; `vendor/bin/pint` avant commit.
- Factories des tables de faits : `organisation_id` et `farm_id` suivent le parent passé en attribut (trait
  `Database\Factories\Concerns\InheritsOrganisationFromParent`) ; `Model::factory()->create(['organisation_id' => $org->id, 'farm_id' => $farm->id])`
  crée toute la chaîne parente dans cette organisation.
- Ne pas ajouter de CRUD brut sur une table de journal ; passer par les services (`FlockService`, `SaleService`, `StockService`, `PayrollService`).
