# AUDIT TECHNIQUE ET FONCTIONNEL — GESTAVI

Date : 19 septembre 2026
Périmètre : dépôt `gestavi` (Laravel 12, Filament 5, Livewire 4, PostgreSQL), 533 fichiers PHP / 44 466 lignes dans `app/`, 87 migrations, 37 ressources Filament panel Ferme + 9 panel Admin, 29 fichiers de tests.
Méthode : lecture directe du code (modèles, services, migrations, ressources, vues, tests), exécution de la suite de tests et de Pint. Chaque constat cite `fichier:ligne`. Ce qui n'a pas pu être vérifié à l'exécution est signalé comme tel.

---

## 1. Synthèse exécutive

GESTAVI est un back-office multi-organisations et multi-fermes d'une ampleur inhabituelle pour un projet de ce stade : élevage, prophylaxie, stocks, achats, ventes, trésorerie, RH et paie sont tous modélisés, avec une base PostgreSQL contrainte proprement et des rôles fins. Le socle d'isolation entre organisations (clients) est solide.

Le produit n'est toutefois pas prêt pour une exploitation réelle. Trois défauts remettent en cause les chiffres qu'il affiche : la mortalité saisie chaque jour ne met jamais à jour l'effectif de la bande (tous les indicateurs techniques en dépendent) ; les écritures de trésorerie, paiements et mouvements de stock peuvent être modifiés ou supprimés sans recalcul ; les rapports financiers et de stock calculent leurs totaux sur un échantillon de 200 lignes.

Le cloisonnement **entre fermes d'une même organisation** n'est effectif que sur 9 ressources sur 37 : un technicien de la ferme A voit la trésorerie, la paie et les factures de la ferme B. Un administrateur peut se promouvoir propriétaire. Les justificatifs de dépenses et d'arrêts maladie sont accessibles publiquement par URL.

La paie applique un plafond CNPS divisé par douze et un barème ITS non sourcé. Aucun module comptable SYSCOHADA n'existe : il s'agit d'une gestion de trésorerie. Il n'y a ni mode hors-ligne, ni SMS, ni intégration mobile money, alors que les plans commerciaux les annoncent.

La suite de tests plante en mémoire, puis présente 20 à 22 échecs non déterministes ; la CI ne peut pas s'exécuter ; le projet n'est pas sous git. Le plan d'action ci-dessous vise d'abord la justesse des chiffres et le cloisonnement, puis l'industrialisation.

---

## 2. Cartographie de l'existant

### 2.1 Stack et structure

| Élément | Constat |
|---|---|
| Framework | Laravel 12 (`composer.json:12`), PHP 8.3.14 local, Filament `5.0` figé (`composer.json:11`), Livewire 4, Tailwind 4, dompdf 3.1 |
| Base | PostgreSQL (`.env`, `phpunit.xml:26`) ; `.env.example:23` indique SQLite |
| Panels | Admin `/admin` (utilisateurs `System`), Ferme `/farm/{slug}` avec tenant = `Organisation` (`app/Providers/Filament/FarmPanelProvider.php:34`) |
| Hiérarchie | Organisation → Ferme → Bâtiment → Bande ; membres affectés aux fermes via `farm_organisation_user` ; rôles d'organisation en base avec poids (`app/Models/OrganisationRole.php`) |
| Feature gating | `Plan` → `PlanFeature` (enum), trait `RequiresPlanFeatures`, middleware `EnsurePlanFeature` |
| Couches | Modèles (54, 7 902 l.), Services (10, 1 772 l.), Ressources Filament (30 989 l.), Policies (18, utilisées seulement côté Admin), Enums (46) |
| Routes | `routes/web.php:5-7` : page d'accueil uniquement ; tout passe par Filament. Aucune API (`routes/api.php` absent) |
| Tâches planifiées | 2 : expiration et alerte de lots de stock (`routes/console.php:14-23`) |
| Jobs asynchrones | 0 (`ShouldQueue` absent de `app/`) |
| Tests | 171 tests Pest (29 fichiers) sur PostgreSQL réel |
| CI | `.github/workflows/tests.yml` et `lint.yml` hérités du starter kit, non adaptés (voir 3.5) |
| Versionnage | Aucun répertoire `.git`, aucun README, aucun Dockerfile |

### 2.2 Modules fonctionnels (ressources du panel Ferme)

- **Exploitation** : Fermes, Bâtiments, Équipements (+ mouvements, maintenances), Fournisseurs (réseau/branches), Journal de ferme.
- **Production** : Bandes, Suivi quotidien, Phases de production, Programmes sanitaires, Vaccinations, Incidents sanitaires, Vétérinaires, Visites vétérinaires.
- **Stocks** : Catégories, Articles, Lots (péremption), Mouvements, Inventaires, Bons de commande.
- **Commercial** : Clients, Devis, Factures (Sales), Paiements, Avoirs.
- **Comptabilité** : Comptes de trésorerie (caisse, banque, mobile money), Transactions, Transferts, Dépenses.
- **RH** : Employés, Contrats (+ avenants), Congés, Présences, Paie.
- **Rapports** : Production, Commercial, Financier, Stocks (Excel + PDF).
- **Paramètres** : Membres, Rôles, Profil d'organisation, Page d'upgrade de plan.

### 2.3 Schéma de données : stratégie de cloisonnement

Base unique partagée, sans schéma par tenant ni RLS. Trois stratégies coexistent :

| Stratégie | Tables |
|---|---|
| `organisation_id` direct (+ `farm_id` nullable) | farms, employees, accounts, expenses, customers, quotes, sales, purchase_orders, credit_notes, stocks, stock_categories, stock_inventories, account_transfers, veterinarians, health_programs, production_phases |
| `farm_id` seul | buildings, vet_visits, farm_logs, equipments |
| **Aucun discriminant** (chaîne de 2 à 4 jointures) | flocks, daily_records, vaccinations, health_incidents, transactions, payments, stock_lots, stock_movements, contracts, leaves, attendances, payrolls, payroll_items, contract_amendments, equipment_movements, equipment_maintenances, quote_items, sale_items, purchase_order_items |

Les tables de saisie quotidienne, les plus volumineuses, sont celles sans discriminant direct : chaque liste exige `whereHas('flock.building.farm', …)` (`app/Filament/Farm/Resources/DailyRecords/DailyRecordResource.php:98`).

---

## 3. Constats détaillés par axe

Format : **[Gravité]** — description — fichier:ligne — impact métier — correction recommandée.

### 3.1 Architecture et modèle de données

**[Critique] A1 — Enums d'équipement incompatibles avec les contraintes CHECK PostgreSQL.**
`app/Models/Equipment.php:231` écrit `status = 'in_maintenance'`, `:93,:136` utilisent `'in_stock'` ; la contrainte n'autorise que `in_service, under_maintenance, out_of_service, retired, disposed` (`database/migrations/2026_03_28_000005_add_check_constraints_to_equipment_tables.php:22`). `Equipment::moveTo()` écrit `movement_type = 'exit'` (`Equipment.php:219`) contre un CHECK `transfer, loan, return, installation, decommission` (`…000005…php:48`). `EquipmentMovementType` contient `entry`/`exit` (`app/Enums/EquipmentMovementType.php:12-13`), `MaintenanceType` contient `cleaning, calibration, replacement` (`app/Enums/MaintenanceType.php:14-16`), `EquipmentCondition::Broken = 'broken'` (`:14`) contre CHECK `damaged`.
Impact : « Mettre en maintenance », « Sortir un équipement », une maintenance de nettoyage ou un état « cassé » lèvent une `QueryException` en production. Non détecté car les CHECK sont ignorés sous SQLite.
Correction : une seule source de vérité (générer la liste SQL depuis `Enum::values()`), test Pest insérant chaque `case` sur PostgreSQL.

**[Critique] A2 — Fuite inter-organisations sur les phases de production.**
`Flock::getCurrentPhase()` interroge `ProductionPhase` par `chicken_type_id` sans filtre `organisation_id` (`app/Models/Flock.php:240-244`), alors que chaque organisation a ses propres phases (`unique(organisation_id, chicken_type_id, code)`, `Organisation::seedDefaultProductionPhases` `app/Models/Organisation.php:419-446`). `first()` renvoie la phase de la première organisation créée.
Impact : consignes d'ambiance (température, densité, aliment) affichées à l'organisation B issues du paramétrage de l'organisation A.
Correction : filtrer sur l'organisation de la bande ; dénormaliser `organisation_id` sur `flocks`.

**[Critique] A3 — Ordre des migrations RH incohérent.**
`employment_type` est supprimé à 18:22 (`2026_03_29_182259_drop_employment_type_from_employees.php:15-16`, gardé par `hasColumn`) puis créé à 20:00 (`2026_03_29_200000_add_missing_fields_to_employees_table.php:22`). Une base rejouée dans l'ordre historique n'a pas la colonne ; une base fraîche l'a. Le modèle l'ignore (`app/Models/Employee.php:20-34`).
Impact : divergence de schéma entre dev, CI et production.
Correction : `php artisan schema:dump --prune` avant la première mise en production ; interdire l'édition de migrations appliquées.

**[Majeur] A4 — Huit migrations RH exécutent des `ALTER TABLE … CHECK` sans garde SQLite**, contrairement au reste du projet : `2026_03_29_100000_create_employees_table.php:63-64`, `100001:46-47`, `100002:36-37`, `100003:33`, `100004:46-47`, `100005:29`, `184201:19`, `200000:33-34`. Avec `.env.example` (`DB_CONNECTION=sqlite`), `composer setup` échoue à la migration `employees`.
Correction : supprimer le support SQLite et aligner `.env.example` sur PostgreSQL.

**[Majeur] A5 — Aucune contrainte ne garantit que deux FK d'une même ligne appartiennent au même tenant.** `expenses.flock_id`/`farm_id`/`organisation_id` (`2026_04_11_175941…php:15-19`), `sales.customer_id`/`account_id`, `stock_movements.flock_id` vs `stock_id`, `vet_visits.flock_id` vs `farm_id`. Le remplissage automatique de `organisation_id` depuis `farm_id` n'agit que si `organisation_id` est null (`app/Models/Sale.php:52`, `Expense.php:47`, `Stock.php:42`) et aucun `saving` ne vérifie `farm.organisation_id === organisation_id`.
Correction : trait `BelongsToOrganisation` avec validation croisée ; à terme FK composites `(organisation_id, farm_id)`.

**[Majeur] A6 — `flocks.code` sans unicité en base** : unique global supprimé (`2026_03_28_000001_fix_unique_codes_to_scoped.php:29-31`) sans remplacement scopé. `equipments.inventory_number` unique par ferme (`2026_03_28_000007…php:38`) alors que le générateur numérote par organisation (`Equipment.php:295`).
Correction : `organisation_id` sur `flocks` + `unique(organisation_id, code)`.

**[Majeur] A7 — Journaux comptable et de stock modifiables, supprimables et cascadés.** `Transaction` et `StockMovement` : `$fillable` complet incluant `balance_before/after` (`app/Models/Transaction.php:17-24`, `StockMovement.php:16-21`), pas de soft delete, `cascadeOnDelete` depuis `accounts` (`2026_04_11_150728…php:15`) et `stocks` (`…203803…php:15`). Supprimer un compte efface son grand livre.
Correction : `restrictOnDelete`, modèle immuable (lever dans `updating`/`deleting`), contrepassation uniquement.

**[Majeur] A8 — Aucune table de faits d'élevage n'a de soft delete et toutes cascadent** : supprimer une bande détruit relevés, vaccinations, incidents (`2026_03_28_150000…php:13`, `160000:13`, `170000:13`) ; supprimer un employé détruit contrats, paies, présences. Le soft delete de `Farm` n'est pas propagé (`Farm` sans hook `deleting`).
Impact : perte d'historique sanitaire et de paie (obligations CNPS).
Correction : SoftDeletes ou `restrictOnDelete` sur les tables de faits ; cascade logique Ferme → Bâtiments → Bandes.

**[Majeur] A9 — Numérotation par `count()+1` sujette aux collisions** : `Customer.php:157-161`, `Stock.php:213-217`, `Quote.php:166-171`, `Expense.php:290-295`, `CreditNote.php:189-194`, `PurchaseOrder.php:177-182`, `AccountTransfer.php:227-232`, `StockInventory.php:213-218`, `Employee.php:218-223`, `Contract.php:132`, `ContractAmendment.php:37`, `Equipment.php:295`. Seule `Sale::generateInvoiceNumber` utilise `MAX()` (`Sale.php:305-317`), sans verrou.
Impact : violation d'unique (erreur 500) en concurrence, doublons sur les tables sans unique (contrats, avenants), régression du compteur après suppression.
Correction : table `sequences(organisation_id, type, year, last_value)` avec `lockForUpdate()`.

**[Majeur] A10 — Types monétaires hétérogènes** : `decimal(15,2)` trésorerie, `decimal(12,2)` ventes, `decimal(10,2)` sur `vaccinations.cost` et `vet_visits` (plafond 99 999 999,99). `currency` n'existe que sur `accounts` (`2026_04_11_150724…php:29`).
Correction : type unique via macro Blueprint.

**[Majeur] A11 — Index manquants** : PostgreSQL ne crée pas d'index sur `foreignId()->constrained()`. Manquent notamment `flocks.chicken_type_id`, `flocks.start_date`, `daily_records.record_date` seul (consolidations par période), `transactions.transaction_date`, `transactions.category`, `sales.farm_id`, `expenses.account_id`, `stock_movements.reason`.

**[Majeur] A12 — Logique métier lourde et dupliquée dans les modèles** : `Sale` (318 l.), `Expense` (297 l.), `AccountTransfer` (234 l.), `StockInventory` (220 l.) portent transactions, verrous et `auth()->id()` (`Account.php:150,180`, `Stock.php:145,183`) alors que des services existent. `Sale::calculateTotals()`/`Quote::calculateTotals()` copiés (`Sale.php:193-212`, `Quote.php:144-161`). Hooks `saved` sur les lignes déclenchant un `update()` du parent par ligne (`SaleItem.php:38-40`).

**[Majeur] A13 — `$fillable` incluant les colonnes dérivées ou sensibles** : `balance_before/after` (`Transaction.php:20`), `current_balance` (`Account.php:24`), `paid/balance` (`Sale.php:25`), `quantity` (`Stock.php:23`), `difference` (`StockInventory.php:23-24`).

**[Mineur] A14** — Index redondants (`daily_records` unique + index identiques, `2026_03_28_150000…php:48-49`) ; `farm_logs.recorded_by` sans `nullOnDelete` (`2026_03_28_200000…php:17`) ; 4 migrations mêlant DDL et données dont une suppression silencieuse de membres (`2026_03_28_000003…php:33-35`) ; colonnes mortes `organisation_users.permissions` et `farms.chicken_type_id` ; CHECK absents sur `vaccinations.status`, `health_incidents.*`, `farm_logs.*`, `buildings.type` ; scopes sans type `Builder` (`Equipment.php:86-125`, `Employee.php:100-113`, `Payroll.php:80-98`) ; `Equipment::getAge()` retourne un float dans `?int` (`:185`) ; 28 modèles sur 55 sans factory.

**Bien fait** : PostgreSQL avec CHECK sur la majorité des statuts et des règles métier réelles (`quantity_current <= quantity_started`, `from_account_id <> to_account_id`, `amount > 0`, `quantity_received <= quantity_ordered`, `farm_id OR organisation_id` sur suppliers) ; unicités scopées par organisation sur les numéros de pièces ; `unique(flock_id, record_date)` et `unique(employee_id, attendance_date)` ; rôles et permissions en base avec hiérarchie ; enums PHP castés ; `CarbonImmutable` et `prohibitDestructiveCommands` (`app/Providers/AppServiceProvider.php:34-38`) ; `down()` écrits partout ; provisionnement automatique des rôles et phases à la création d'organisation.

### 3.2 Couverture du métier avicole

#### Production et prophylaxie

**[Critique] P1 — L'effectif courant n'est jamais décrémenté par la saisie quotidienne.**
`FlockService::recordDailyData()` décrémente `quantity_current` (`app/Services/FlockService.php:252-256`) mais n'est appelé nulle part (`grep recordDailyData app/Filament` : 0). `CreateDailyRecord::mutateFormDataBeforeCreate` pose seulement `recorded_by` (`app/Filament/Farm/Resources/DailyRecords/Pages/CreateDailyRecord.php:20-25`). `DailyRecord` et `Flock` n'ont aucun observer.
Impact : taux de mortalité (`Flock.php:174-183`), survie du dashboard (`app/Filament/Farm/Widgets/StatsOverviewWidget.php:71-86`), taux de ponte (`DailyRecord.php:87-95`), IC (`FlockService.php:404`), coût par sujet (`Flock.php:220-227`) sont faux dès la première mortalité saisie.
Correction : dériver l'effectif (`quantity_started − Σ mortality − Σ réformes − Σ ventes de sujets`) plutôt que de le stocker ; recalcul sur édition et suppression d'un relevé.

**[Critique] P2 — Les programmes sanitaires de référence ne s'appliquent à aucune bande.**
Le seeder crée les programmes avec `organisation_id = null` (`database/seeders/HealthProgramSeeder.php:45,181,439`) ; `HealthProgram::getProgramForFlock()` filtre `where('organisation_id', $farm?->organisation_id)` strict (`app/Models/HealthProgram.php:200,216`). Aucune copie à la création d'organisation (`Organisation.php:295-298` ne copie que les phases).
Impact : le calendrier vaccinal généré à la création de bande (`CreateFlock.php:17-27`) est vide pour toute organisation n'ayant pas ressaisi son programme.
Correction : `orWhereNull('organisation_id')` ou clonage des programmes système dans `Organisation::created`.

**[Majeur] P3 — Formule d'indice de consommation incohérente.** `FlockService.php:392-412` : aliment cumulé sur toute la vie divisé par le gain de poids entre première et dernière pesée, multiplié par l'effectif courant (faux, cf. P1) ; ignore le poids des sujets morts et le poids d'arrivée si la première pesée est tardive.
Correction : IC = Σ aliment(période) / [(poids moyen final × effectif final) − (poids initial × effectif initial)], poids initial par défaut 40 g.

**[Majeur] P4 — Taux de ponte moyen biaisé.** `FlockService.php:417-431` : moyenne des taux journaliers calculés avec l'effectif actuel (pas celui du jour), en excluant les jours à 0 œuf (`:421`).
Correction : Σ œufs / Σ poules présentes par jour ; stocker `birds_present` dans `daily_records`.

**[Majeur] P5 — Aucun indicateur zootechnique n'est visible.** `calculatePerformanceIndicators()` (`FlockService.php:265-310`) n'est appelé nulle part ; `FlockInfolist.php:102-131` n'affiche que phase, âge, effectifs ; `ProductionReportPage::getKpis()` (`app/Filament/Farm/Pages/Reports/ProductionReportPage.php:178-193`) n'a ni pourcentage, ni IC, ni GMQ, ni taux de ponte. La feature `ZootechnicalCalculations` (`app/Enums/PlanFeature.php:17`) est vendue sans écran.

**[Majeur] P6 — Délai d'attente non appliqué.** `withdrawal_days` existe sur `health_programs` (`2026_03_27_212344…php:44`) mais n'est ni copié sur `vaccinations` (`FlockService.php:99-109`) ni contrôlé à la vente (`SaleService.php` : 0 occurrence).
Impact : risque réglementaire de résidus dans viande et œufs.
Correction : colonne `withdrawal_until` calculée à `markAsDone`, garde-fou dans `SaleService` et à la clôture de bande.

**[Majeur] P7 — Doublon de relevé = erreur 500.** Contrainte unique en base (`2026_03_28_150000…php:48`) sans `Rule::unique` dans `DailyRecordForm.php:107-118`.

**[Majeur] P8 — Statut de bande modifiable librement.** `FlockForm.php:148-153` : `Select status` actif en création et édition, contournant `startFlock/completeFlock/cancelFlock` (journal, vaccinations, `actual_end_date`).

**[Majeur] P9 — Aucune notification ni tâche planifiée pour les vaccinations en retard** : `routes/console.php:14-23` ne planifie que les stocks ; `app/Notifications` inexistant.

**[Majeur] P10 — Mortalité assimilée à toute baisse d'effectif** : `Flock::getMortalityRate()` = `(started − current)/started` (`Flock.php:174-183`) ; `DailyRecord::getMortalityRate()` divise par l'effectif initial (`DailyRecord.php:129-134`) au lieu de l'effectif présent ; `HealthIncident.birds_dead` (`HealthIncident.php:29`) n'est jamais répercuté.

**[Mineur] P11** — Réforme (culls), uniformité, œufs déclassés, poids d'œuf absents de `daily_records` (`2026_03_28_150000…php:18-45`) ; IPE/EPEF, œufs par poule logée, standards de souche absents ; type détecté par `str_contains(name, 'ponde'/'chair')` (`FlockService.php:294-307`) et `=== 'Pondeuse'` (`DailyRecordForm.php:163`), le type « Mixte » n'a ni ponte ni IC ; pas de transfert de bâtiment ni de bande multi-bâtiments ; incident sanitaire déconnecté de l'effectif et du stock ; `getCurrentPhase()` = 1 requête par ligne (`FlocksTable.php:67-73`, `ActiveFlocksWidget.php:81-85`) ; vue orpheline `resources/views/filament/farm/flocks/vaccination-timeline.blade.php` ; code de bande non atomique (`FlockService.php:338-359`) ; aucun test sur `DailyRecord`, `Vaccination`, `HealthProgram`, `FlockService`.

**Bien fait** : modèle riche (bandes, relevés, phases, programmes, vaccinations, incidents, visites, journal) ; workflow de bande transactionnel et journalisé (`FlockService.php:29-70,127-144,158-190`) ; génération idempotente du calendrier vaccinal tenant compte de l'âge à l'arrivée (`:78-115`) ; phases par défaut réalistes chair et pondeuse (`Organisation.php:26-160`) ; formulaires contextualisés (bande active, recalcul de l'âge) ; widgets avec agrégation SQL (`ProductionChartWidget.php:42-49`) et seuils 2 %/5 % ; exports PDF/Excel FR.

#### Stocks, achats, ventes

**[Critique] S1 — La création de facture et de bon de commande via l'interface ne peut vraisemblablement pas fonctionner.** `SaleForm.php:125-127` et `PurchaseOrderForm.php:106-108` utilisent `Repeater::make('items')->relationship()` ; Filament force alors `dehydrated(false)` (`vendor/filament/forms/src/Components/Repeater.php:1013`), donc `$data['items']` est vide dans `CreateSale::handleRecordCreation` (`app/Filament/Farm/Resources/Sales/Pages/CreateSale.php:19`) et `SaleService::createSale` lève `emptyItems()` (`app/Services/SaleService.php:26-27`) → `halt()`.
Non confirmé à l'exécution (un test Livewire temporaire a échoué sur un verrou de la base de test partagée) ; la lecture du code vendor le rend très probable. Aucun test ne couvre la page de création.
Correction : retirer `->relationship()` (lignes gérées par le service) ou supprimer `handleRecordCreation` et recalculer dans `afterCreate()` ; test Livewire `CreateSale`/`CreatePurchaseOrder`.

**[Critique] S2 — Mouvements de stock fantômes.** `CreateStockMovement` est un `CreateRecord` vide (`app/Filament/Farm/Resources/StockMovements/Pages/CreateStockMovement.php:8-11`) : insère un mouvement sans toucher `Stock.quantity`, contrairement à `Stock::recordIn/recordOut` (`app/Models/Stock.php:143-198`). `CreateStockLot` idem. Mouvements éditables et supprimables (`EditStockMovement.php:8-9`, `StockMovementsTable.php:92`, `MovementsRelationManager.php:111`).
Impact : quantité affichée ≠ somme des mouvements ≠ somme des lots.
Correction : supprimer les pages create/edit/delete, ou déléguer à `recordIn/recordOut` ; mouvement d'annulation au lieu de suppression.

**[Majeur] S3 — FIFO/FEFO jamais utilisé.** `StockService::consumeFromLots` (`app/Services/StockService.php:123-170`) et `scopeFifo` (`StockLot.php:83-87`) ne sont appelés nulle part ; les sorties UI (`ViewStock.php:59-116`) ignorent les lots.

**[Majeur] S4 — Pas de valorisation (CMUP/FIFO).** Valeur = `quantity × unit_price` figé (`Stock.php:119-122`) ; `recordIn` ne met pas à jour `unit_price` (`Stock.php:142-160`) ; `StockLot.total_value` figé à la création (`StockLot.php:44`).

**[Majeur] S5 — Réception de bon de commande incomplète.** Le lot n'est créé que si `create_lot` et `lot_number` sont fournis (`StockService.php:81-95`), or l'action UI n'envoie que `item_id`/`quantity` (`ViewPurchaseOrder.php:69-77`) : aucun lot ni péremption depuis l'UI, réception toujours totale. Aucun lien réception → dette fournisseur/dépense.

**[Majeur] S6 — Aucun lien stock ↔ élevage.** `DailyRecord.feed_consumed_kg` (`DailyRecord.php:24`) ne déstocke rien ; vaccinations et traitements ne déstockent aucun médicament ; `SaleItem.flock_id` (`SaleItem.php:14,54-57`) est informatif, la vente de sujets vivants ne décrémente pas la bande ni le stock.

**[Majeur] S7 — Paiements supprimables sans effet.** `Payment` sans hook (`app/Models/Payment.php`), `DeleteAction` (`EditPayment.php:8`) et `DeleteBulkAction` (`PaymentsTable.php:84`, `PaymentsRelationManager.php:90`) ne recalculent ni `Sale.paid/balance/status` ni le compte crédité.

**[Majeur] S8 — Statut de facture saisissable librement.** `SaleForm.php:267-271` propose tous les `SaleStatus` ; une facture peut naître « Payée » avec `paid = 0` ; édition possible d'une facture payée (`ViewSale.php:107`).

**[Majeur] S9 — Concurrence non maîtrisée.** `Stock::recordOut` vérifie la disponibilité hors transaction (`Stock.php:177-179` puis `DB::transaction` `:181`) ; `consumeFromLots` idem (`StockService.php:129-131`) ; `recordPayment` lit `balance` sans verrou (`SaleService.php:95-99`).

**[Majeur] S10 — Avoirs.** `CreditNote::apply` débite le compte de trésorerie du montant imputé sur une créance non encaissée (`app/Models/CreditNote.php:133-145`) : la caisse est diminuée d'argent jamais sorti ; sur une facture déjà payée, `remaining = 0` et l'avoir est marqué appliqué sans remboursement ; le CA du rapport commercial reste surévalué.

**[Majeur] S11 — Solde client incohérent.** `Sale::updatePaymentStatus()` somme les `balance` de toutes les ventes y compris annulées et brouillons (`Sale.php:233-237`) ; `Sale::cancel()` ne remet pas `balance` à 0 (`:278-298`).

**[Majeur] S12 — Rapports commercial/stock : tout en mémoire, tronqués et non consolidés.** `CommercialReportPage.php:89-95`, `StockReportPage.php:103-109` chargent toute la période ; `getMovements()` tronqué à 200 (`StockReportPage.php:127`) fausse entrées/sorties (`:138-139`) ; aucun regroupement par ferme.

**[Mineur] S13** — `PaymentMethod` sans Moov Money (`app/Enums/PaymentMethod.php:11-17` vs `MobileMoneyOperator.php:12`) ; permission `sales.record_payment` seedée mais l'action vérifie `update` (`ViewSale.php:28`) ; pas de référentiel produits (œufs par plateau, poulet vif/abattu au kg) : lignes en texte libre (`SaleItem.php:14-16`) ; pas de livraisons ni d'abattage malgré `PlanFeature::Deliveries` (`PlanFeature.php:40`) ; TVA par défaut 0 % sans mentions fiscales NCC/RCCM sur le PDF (`resources/views/pdf/commercial-document.blade.php:165-176`) ; pas de transfert inter-fermes de stock ; pas de catégorie litière ; aucune notification de stock bas ; `CreatePayment` fait `Sale::findOrFail($data['sale_id'])` sans vérification tenant (`CreatePayment.php:8`) ; totaux en float puis cast `decimal:2` sans arrondi à l'unité XOF (`SaleItem.php:63-70`).

**Bien fait** : contraintes `quantity >= 0`, `quantity_received <= quantity_ordered`, `amount > 0` ; `DB::transaction` systématique dans les services ; exceptions métier en français ; numérotation facture par `MAX()` (`Sale.php:305-317`) ; contrôle du plafond de crédit client (`SaleService.php:41-55`) ; garde-fous `canBeReceived/canBeCancelled/canBeApplied` ; contrepassation à l'annulation de facture ; tâches planifiées de péremption avec `withoutOverlapping` ; PDF/Excel en XOF et `d/m/Y`.

#### Finance, comptabilité, RH

**[Critique] F1 — Suppression et modification physiques des transactions.** `TransactionsTable.php:104-108` et `EditTransaction.php:18` exposent `EditAction`, `DeleteBulkAction` sans condition ; `Transaction.php` sans hook `deleting` ; `TransactionForm.php:34-78` laisse modifier `account_id`, `type`, `amount` ; `ForceDeleteAction` sur comptes (`ViewAccount.php:56`) avec cascade.
Impact : `current_balance` désynchronisé de l'historique, disparition de pièces ; incompatible avec l'intangibilité des écritures SYSCOHADA.
Correction : `canEdit/canDelete = false` sur Transaction et Payment ; seule voie = `TransactionService::reverseTransaction` (`app/Services/TransactionService.php:57-93`) ; interdire le force delete d'un compte ayant des transactions.

**[Critique] F2 — Plafond CNPS divisé par douze à tort.** `app/Services/PayrollService.php:11-12` : `CNPS_CEILING_MONTHLY = 137_276` commenté « 1 647 315 FCFA / 12 mois ». 1 647 315 FCFA était déjà un plafond **mensuel** (45 × SMIG 36 607) ; il est aujourd'hui de 3 375 000 FCFA/mois (SMIG 75 000). Toute cotisation est plafonnée à 8 648 FCFA (`:138-153`).
Impact : bulletins faux, net surestimé, redressement CNPS.
Correction : table de paramètres de paie versionnée par date d'effet (plafond, taux, branches employeur : prestations familiales 5,75 %, accident du travail 2 à 5 %) ; validation par un expert-comptable.

**[Critique] F3 — Paie déconnectée de la trésorerie et des coûts.** `Payroll::markAsPaid` ne fait qu'un `update` de statut (`app/Models/Payroll.php:151-158`) ; aucune `Transaction`, aucune `Expense`, aucun débit de compte. La catégorie `TransactionCategory::Payroll` n'est atteinte que par ressaisie manuelle.
Correction : `markAsPaid(userId, accountId)` dans une `DB::transaction` créant la dépense « salaires » (ferme de l'employé) et débitant le compte.

**[Critique] F4 — Race condition sur `Account::credit/debit`.** `app/Models/Account.php:146-163,176-193` : `balance_before` lu en mémoire puis `update`, sans `lockForUpdate` ni transaction interne. Appelé sans verrou par `Sale::addPayment` (`Sale.php:260`), `Sale::cancel` (`:282`), `Expense::cancel` (`:270`), `CreateTransaction.php:26-37`. `AccountTransfer::complete()` prend ses verrous hors de la `DB::transaction` (`AccountTransfer.php:132-133` vs `:149`).
Impact : deux encaissements simultanés (deux caissiers, mobile money) écrasent le solde.
Correction : `credit/debit` ouvrent une transaction, rechargent avec `lockForUpdate()`, puis écrivent.

**[Majeur] F5 — KPI du rapport financier calculés sur 200 lignes.** `FinancialReportPage.php:155` `->limit(200)` puis `getKpis()` (`:175-188`) somme cette collection ; PDF tronqué à 100 lignes (`:234`) sans mention. Le « solde net » n'est ni un résultat ni un solde de trésorerie (pas de solde d'ouverture).
Correction : agrégats SQL, libeller « flux de trésorerie », ajouter soldes d'ouverture/clôture par compte.

**[Majeur] F6 — Barème ITS non conforme et non sourcé.** `PayrollService.php:159-173` : tranches 240 000 → 500 000 → 1 M → 2 M → 3,5 M à 21/24/28/32/36 %. Le barème unique ITS (annexe fiscale 2024 : 75 000 / 240 000 / 800 000 / 2 400 000 / 8 000 000 à 0/16/21/24/28/32 %) et la réduction pour charges de famille ne correspondent pas. Aucune source ni date dans le code ; impossible de conclure quelle version est visée.
Correction : table `payroll_tax_brackets` versionnée, parts fiscales sur `employees`, validation externe.

**[Majeur] F7 — Absence de comptabilité générale SYSCOHADA.** Aucun plan comptable, journal, écriture en partie double, exercice, clôture ; `Transaction` est un mouvement mono-compte (`Transaction.php:17-24`) ; pas de TVA déductible sur dépenses (`2026_04_11_175941`, aucun champ taxe) ; TVA 18 % non proposée par défaut (`Sale.php:200-203`) ; aucun export FEC/Sage (seul un Excel de transactions, `FinancialReportPage.php:190-220`). Le module est une gestion de trésorerie.
Correction : au minimum un mapping catégorie → compte SYSCOHADA (ex. aliment → 6011, ventes → 701) et un export journal par période ; à terme partie double.

**[Majeur] F8 — Coût de revient par bande absent.** Seul `Flock::getCostPerCurrentUnit()` = prix d'achat des poussins / effectif courant (`Flock.php:220-227`). Les données existent pour le construire : `expenses.flock_id`, `stock_movements.flock_id` (`2026_04_11_203803…php:18`), `sale_items.flock_id`, `daily_records.feed_consumed_kg`, `flocks.total_purchase_price`. Aucune ventilation de charges indirectes, aucune marge par bande.

**[Majeur] F9 — Statut de dépense saisissable et suppression sans contrepassation.** `ExpenseForm.php:190-195` : `Select status` libre ; une dépense créée « Payée » sans `pay_now` a `balance = amount` et aucune transaction. `DeleteAction`/`ForceDelete` disponibles quel que soit le statut (`EditExpense.php:20-22`, `ExpensesTable.php:129-131`).

**[Majeur] F10 — Dates comptables écrasées.** `Account::credit/debit` forcent `transaction_date = now()` (`Account.php:160,190`) alors que `Sale::addPayment` accepte `$paidAt` (`Sale.php:245,255`). Une rétro-saisie tombe dans le mauvais mois.

**[Majeur] F11 — Paie : `other_deductions` saisi (`PayrollForm.php:200`) jamais intégré au net (`Payroll.php:129-138`) ; `worked_days` manuel sans lecture des présences ; bulletin PDF absent malgré `PlanFeature::PayslipPdf` (`PlanFeature.php:68`, aucune vue `pdf/payslip*`) ; aucune déclaration CNPS/ITS ; paie journalière impossible (`EmploymentType::Daily` orphelin).**

**[Majeur] F12 — Aucun test sur paie, congés, contrats, présences** (`grep Payroll|Employee|Attendance tests/` : vide).

**[Mineur] F13** — Congés : maternité `maxDays = 14` (`app/Enums/LeaveType.php:40-49`) alors que la loi prévoit 14 semaines ; `maxDays()` jamais appelé ; `Employee::getRemainingAnnualLeaveDays` compte les congés annulés (`Employee.php:187`) ; jours calendaires au lieu d'ouvrables (`LeaveService.php:38-41`). `EmployeeService::log` écrit `category = 'rh'` hors enum `FarmLogCategory` (`EmployeeService.php:64`, `FarmLogCategory.php:11-23`) → `ValueError` à la lecture. `AccountForm.php:92-104` propose USD absent de `currencies` (`CurrencySeeder.php:15-23`). Rapprochement bancaire sans solde de relevé, `is_reconciled` saisissable à la création (`TransactionForm.php:120`). `CashFlowWidget.php:54-66` : 24 requêtes pour 12 mois. `TransactionService::detectPotentialDuplicates` (`:194-243`) non exposé.

**Bien fait** : `balance_before/after` sur chaque mouvement et références polymorphes vente → paiement → transaction (`Sale.php:264-266`) ; contrepassation dans les services (`reverseTransaction`, `AccountTransfer::reverse`, `Expense::cancel`) et refus de contrepasser une transaction rapprochée (`TransactionService.php:59-61`) ; `verifyOrganisationIntegrity` et action « Recalculer le solde » (`ViewAccount.php:37-45`) ; unicité paie par employé et période ; avenants avec `previous_values/new_values` ; formatage XOF cohérent (`AccountsTable.php:60`) ; comptes mobile money par opérateur (`AccountType.php:11-14`, `MobileMoneyOperator.php:10-13`).

### 3.3 Reporting et consolidation

**[Majeur] R1 — Aucune consolidation multi-fermes.** Les 4 pages de rapport filtrent par organisation seulement, sans colonne ni filtre ferme, sans `groupBy farm_id` (`ProductionReportPage.php:100`, `CommercialReportPage.php:36`, `FinancialReportPage.php:90,151`, `StockReportPage.php:52,105`). Aucune comparaison inter-sites, aucun classement.

**[Majeur] R2 — KPI sur échantillons tronqués ou en mémoire** (voir F5, S12) : `FinancialReportPage.php:155-156`, `StockReportPage.php:170-171` limités à 200 ; `ProductionReportPage.php:169-176` et `CommercialReportPage.php:127-134` chargent tout puis agrègent en PHP. Seul `getExpensesByCategory` (`FinancialReportPage.php:169-172`) fait un vrai `GROUP BY`. `getKpis()` est rappelé plusieurs fois par rendu (`commercial-report.blade.php:7,48`).

**[Majeur] R3 — Génération PDF/XLSX synchrone** dans la requête Livewire, 9 points d'appel (`ProductionReportPage.php:239`, `FinancialReportPage.php:229`, `ViewSale.php:36`, `ViewQuote.php:32`, `ViewExpense.php:35`, `ViewPurchaseOrder.php:32`, `ViewStockInventory.php:30`, `ViewContract.php:40`, `ContractsTable.php:111`). Aucun job, aucun cache.

**[Majeur] R4 — Rapports et widgets sans contrôle d'accès ni scope ferme** (voir X3).

**[Mineur] R5** — Formatage monétaire hétérogène : 30 `->money('XOF')` (rendu `XOF 1,234`), 106 `number_format` manuels, 42 `FCFA` et 61 `'XOF'` en dur ; 8 `->date()` sans format (`EquipmentsTable.php:99`, `FlocksTable.php:65`, `EquipmentInfolist.php:107,122,136,141`) sortant en `M j, Y` anglais ; aucun `Carbon::setLocale` (mois en anglais). `openspout/openspout` utilisé (`ProductionReportPage.php:21-22`) sans être déclaré dans `composer.json`. `ProductionReportPage::exportPdf()` fait `Flock::find($this->filters['flock_id'])` sans vérification tenant (`:237`).

**Bien fait** : export XLSX en streaming (`ProductionReportPage.php:200-202`, `php://output`) ; PDF A4 avec dates `d/m/Y` et milliers à l'espace ; filtres période et bande ; widgets avec `->with()` (`ActiveFlocksWidget.php:31`, `VaccinationCalendarWidget.php:45`).

### 3.4 Sécurité et intégrité

#### Cloisonnement inter-organisations : solide

Chaque ressource surcharge `scopeEloquentQueryToTenant` avec `organisation_id` ; Filament l'applique à `resolveRecord` (ID d'une autre organisation → 404). Les `->options()` sont filtrés par tenant et Filament 5 valide la valeur soumise contre ces options (`vendor/filament/forms/src/Components/Concerns/CanBeValidated.php:815`). Les PDF passent par `streamDownload` dans des actions authentifiées ; aucune route publique par ID (`routes/web.php:5-7`). Le test `TenantIsolationTest` le confirme pour `Sale`.

**[Majeur] X1 — `EmployeeForm.php:258-260` : `Select user_id ->relationship('user')` sans `modifyQueryUsing`** → liste tous les utilisateurs de la plateforme (autres organisations, administrateurs) et permet de lier un employé à un compte externe.

#### Cloisonnement intra-organisation par ferme : incomplet

**[Critique] X2 — 28 ressources sur 37 ne respectent pas l'affectation aux fermes.** Seules Farms, Buildings, Flocks, DailyRecords, Vaccinations, HealthIncidents, FarmLogs, Equipments, Suppliers appliquent `accessibleBy($membership)` (ex. `FlockResource.php:93-106`, `DailyRecordResource.php:93-106`). Accounts, Customers, Expenses, PurchaseOrders, Quotes, Sales, Stocks, Employees, Transactions, Payments n'appliquent `farm_id` que `when($currentFarm)` (`SaleResource.php:93-101`, `AccountResource.php:85-93`, `EmployeeResource.php:92-99`) ; or `CurrentFarm::get()` renvoie `null` (= toutes les fermes) dès que le membre a accès à deux fermes sans en sélectionner une (`app/Services/CurrentFarm.php:39-54`). Attendances, Contracts, Leaves, Payrolls, AccountTransfers, CreditNotes, StockLots, StockMovements, StockInventories, VetVisits ignorent totalement la ferme (`ContractResource.php:88-90`, `PayrollResource.php:87-89`, `StockLotResource.php:83-85`, `VetVisitResource.php:90-92`). Les Select de comptes ne filtrent que par organisation (`ExpenseForm.php:174-184`, `TransactionForm.php:34-46`, `AccountTransferForm.php:144-158`, `PaymentForm.php:66-72`).
Impact : un technicien ou caissier de la ferme A lit et modifie factures, comptes bancaires, bulletins de paie et stocks de la ferme B ; il peut débiter la caisse d'une autre ferme.
Correction : exposer `accessibleFarmIds()` dans `ScopesToMemberFarms` et l'appliquer dans tous les `scopeEloquentQueryToTenant` (`whereIn('farm_id', ids)` + `orWhereNull` réservé aux rôles à accès global), scoper les enfants via la relation parente, filtrer les Select de comptes/employés par ferme, un test Pest par ressource.

**[Majeur] X3 — Dashboard et rapports sans permission ni scope ferme.** Les 4 pages `app/Filament/Farm/Pages/Reports/*.php` n'ont ni `canAccess()` ni trait de permission ou de plan ; les 12 widgets n'ont aucun `canView()` ; agrégation au niveau organisation (`StatsOverviewWidget.php:196-206`, `CashFlowWidget.php:33-35`, `FinancialReportPage.php:84-91`). Un employé sans `transactions.view` voit trésorerie, CA, impayés et télécharge le rapport financier.

#### RBAC

**[Majeur] X4 — Escalade de privilèges par un administrateur.** (a) `MemberForm.php:79-88` propose tous les rôles de l'organisation, dont `owner`, sans comparer au poids de l'acteur. (b) `OrganisationRoleForm.php:182-188` : `weight` libre 1 à 10 → un admin avec `roles.create` crée un rôle de poids supérieur à owner et se l'attribue ; `canAccessAllFarms()` et `isAdmin()` reposent sur ce poids (`OrganisationUser.php:124-127`). (c) `OrganisationRolesTable.php:54` : édition des rôles `is_system` dès `roles.update`. `OrganisationPolicy::changeRole` contient la bonne logique (`OrganisationPolicy.php:98-117`) mais n'est jamais appelée (aucun `Gate::`/`authorize` dans `app/`).
Correction : rôles proposés bornés à `weight < acteur`, `weight` borné, rôles système non éditables sauf owner, `Gate::authorize('changeRole')` dans `EditMember`.

**[Majeur] X5 — Permissions fines seedées mais non vérifiées** : `transactions.reconcile/reverse`, `account_transfers.complete/cancel/reverse`, `accounts.set_default/recalculate_balance`, `sales.record_payment` existent (`OrganisationPermissionSeeder.php`) mais les actions vérifient `update`/`delete` (`ViewTransaction.php:26,37,47`, `ViewAccountTransfer.php:25,52,63`, `ViewAccount.php:26,41`, `ViewSale.php:54`). L'écran de rôles laisse croire à un contrôle qui n'existe pas.

**[Mineur] X6** — `ChecksOrganisationPermissions::canEdit/canView/canDelete` ignorent `$record` (`app/Filament/Farm/Concerns/ChecksOrganisationPermissions.php:33-46`) : l'appartenance repose uniquement sur le scope de requête. `OrganisationOwnedPolicy::belongsToUserOrganisation` retourne `true` si `organisation_id` est null (`app/Policies/OrganisationOwnedPolicy.php:61-71`). `OrganisationPolicy` sans `viewAny`/`create` → tout utilisateur `System` liste et crée des organisations. `EnsureApiPlanFeature` (alias `plan.feature`, `bootstrap/app.php:14-16`) n'est utilisé par aucune route et choisit « la première organisation active » (`EnsureApiPlanFeature.php:141-143`) : code mort.

#### Authentification

**[Majeur] X7 — Pas de 2FA, pas de réinitialisation ni de vérification d'e-mail** (`->multiFactorAuthentication`, `->passwordReset()`, `->emailVerification()` absents des deux `PanelProvider`). Pour un panel pilotant trésorerie et paie, 2FA au moins pour owner/admin.

**[Majeur] X8 — Mots de passe des membres validés `minLength(8)` seulement** (`MemberForm.php:40-45,61-65`) : la politique forte de production (`AppServiceProvider.php:40-48`) ne s'applique pas aux comptes créés par les fermes.

**[Mineur] X9** — Comptes démo avec mot de passe `password` (`UserFactory.php:31`) ; `DatabaseSeeder` exécutable en production (`prohibitDestructiveCommands` ne bloque pas `db:seed`).

**Bien fait** : `canAccessPanel` bloque `is_active = false` et sépare strictement System/Farmer (`app/Models/User.php:126-137`) ; `canAccessTenant` exige un membership actif (`:114-120`) ; suspension des memberships à la soft-delete d'un utilisateur ; rate limiting Filament natif sur le login ; `Password::defaults` fort en production ; `AuthenticateSession` sur les deux panels ; permission par ressource avec cache par requête (`ChecksOrganisationPermissions.php:82-85`) ; owner bypass ; actions métier gardées par `canPerformAction()`.

#### Secrets, validation, uploads

**[Majeur] X10 — Justificatifs sur disque public.** Dépenses (`ExpenseForm.php:158`) et congés/arrêts maladie (`LeaveForm.php:138`) sur `disk('public')`, servis via `Storage::url()` (`ExpenseInfolist.php:110`, `LeaveInfolist.php:84`) → `/storage/...` accessible sans authentification, hors de tout scope.
Correction : disque privé + action `streamDownload` avec vérification tenant.

**[Majeur] X11 — Pièces d'identité et photos des employés sans `->disk()`** (`EmployeeForm.php:95-113`) → disque `local` privé (bon), mais `ImageColumn::make('photo')` (`EmployeesTable.php:25`) ne pourra pas résoudre d'URL : soit cassé, soit quelqu'un basculera en `public` et exposera des pièces d'identité. Trancher : disque privé + URL signée temporaire.

**[Mineur] X12** — `phpunit.xml:31` contient un mot de passe PostgreSQL en clair ; `.env` local `APP_DEBUG=true`, `SESSION_ENCRYPT=false`. `.env` bien ignoré (`.gitignore:11-13`) ; `.mcp.json` et `.claude/settings.local.json` sans secret.

**Bien fait** : `$fillable` explicite partout ; aucun `{!! !!}` non échappé (les occurrences sont `nl2br(e(...))`, ex. `commercial-document.blade.php:284`) ; SQL brut uniquement sur constantes (`Sale.php:313`, `LowStockWidget.php:30`) ; `acceptedFileTypes` + `maxSize` sur tous les uploads.

#### Piste d'audit et intégrité des saisies

**[Majeur] X13 — Aucun journal d'activité** (pas de `spatie/laravel-activitylog` ni équivalent), pas de `updated_by`, SoftDeletes uniquement sur Employee, User, Organisation, Farm et quelques tables commerciales. `DeleteBulkAction`/`ForceDeleteBulkAction` sur Sales, Expenses, Accounts, CreditNotes (`SalesTable.php:106-107`, `AccountsTable.php:109-110`). Un `DailyRecord` est éditable et supprimable sans motif ni trace (`EditDailyRecord.php:14-20`).
Correction : activity log sur les modèles financiers, stock et production ; interdiction de suppression physique ; correction rétroactive des relevés limitée dans le temps avec motif.

**Bien fait** : colonnes d'imputation (`recorded_by`, `user_id`, `validated_by`, `approved_by`) ; machines d'états sur paie, inventaires, avoirs avec édition limitée au brouillon (`PayrollsTable.php:95-96`, `ViewStockInventory.php:81-82`) ; `DB::transaction` dans les 6 services ; unicités anti-doublon en base ; contrainte `supplier organisation_or_farm`.

### 3.5 Qualité, performance, exploitation

#### Tests

**[Critique] Q1 — La suite ne s'exécute pas avec la configuration du projet.** `php artisan test --compact` meurt en `Allowed memory size of 134217728 bytes exhausted` (Str.php:96). Aucune directive `memory_limit` dans `phpunit.xml`. La CI (`tests.yml:60` `./vendor/bin/pest`) échouera de même.

**[Critique] Q2 — 20 à 22 échecs non déterministes.** Avec `php -d memory_limit=2G vendor/bin/pest` : run 1 = 22 échecs / 149 réussis (196,7 s) ; run 2 identique = 20 échecs / 151 réussis, ensembles d'échecs différents. Causes identifiées :
- `database/factories/FlockFactory.php:41,45,85` utilise `supplier_name` et `is_active`, colonnes supprimées par `2026_03_27_182810_update_flocks_table_v2.php` → 7 tests `FlockResourceTest` en `Undefined column`.
- `tests/Feature/SupplierResourceTest.php:105` : `addMember($employee, null)`, le rôle `employee` introuvable → `TypeError` (`Organisation.php:498`) ; même symptôme intermittent dans `FarmAccessScopeTest` (9 `TypeError` au run 2, 0 au run 1) → création des rôles système en test non fiable.
- 3 tests de création fournisseur violent la CHECK `suppliers_organisation_or_farm_check` : soit le formulaire (`SupplierForm.php:72`) laisse créer un fournisseur orphelin, soit le test est obsolète.
Impact : base de tests « rouge permanent », régressions invisibles.

**[Critique] Q3 — Zéro test sur les règles avicoles et RH.** Aucun test ne référence `DailyRecord`, `Vaccination`, `HealthProgram`, `Payroll`, `Employee`, `FlockService`, ni les pages `Reports/*`. Non couverts : décrément d'effectif, IC/GMQ/ponte, génération du calendrier vaccinal, CNPS/ITS/`grossFromNet`, congés, `reverseTransaction`, `verifyAccountIntegrity`, `consumeFromLots`, création via pages Filament (Repeater), inventaires, avoirs, plafond crédit, suppression de paiement, concurrence.

**[Majeur] Q4 — Cloisonnement par ferme quasi non testé** : `TenantIsolationTest` (4 tests, `Sale`, niveau organisation) ; `FarmAccessScopeTest` (13 tests, scope `accessibleBy` sur `Farm` uniquement) ; le seul test « membre restreint » sur une ressource (`SupplierResourceTest.php:99`) échoue.

**[Mineur] Q5** — `tests/Unit/*` = tests d'énums et de `$fillable` ; `ExampleTest.php` ×2 conservés ; stub `function something()` dans `tests/Pest.php:52`.

**Bien fait** : tests sur PostgreSQL réel (CHECK et `jsonb` exercés) ; vrais tests d'intégration sur AccountService, SaleService, StockService ; pattern `setCurrentPanel + setTenant` correct pour Filament.

#### CI/CD et exploitation

**[Majeur] Q6 — CI inexécutable.** `.github/workflows/tests.yml` : matrice PHP 8.4/8.5 (`:23`) contre 8.3.14 local ; `cp .env.example .env` (`:51`) → SQLite alors que `phpunit.xml:26-31` force PostgreSQL `127.0.0.1` sans service déclaré ; étapes Flux UI inutiles (`:44-45`) ; `lint.yml:44` `composer lint` = `pint --parallel` qui **corrige** au lieu de `--test`.
Correction : service `postgres:16`, variables `DB_*`, PHP 8.3, `pint --test`, retirer Flux.

**[Majeur] Q7 — Ni git, ni README, ni Dockerfile, ni sauvegarde, ni supervision.** Aucun `.git` ; aucun `spatie/laravel-backup`, `sentry`, `pulse`, `telescope`, `horizon` dans `composer.lock`. Impact : pas de traçabilité des changements, pas de rollback, données d'élevage non sauvegardées.

**[Majeur] Q8 — Fuseau horaire `UTC`** (`config/app.php:68`) pour des saisies datées, des `dailyAt('03:30')` et des échéances pensées heure d'Abidjan. Correct par coïncidence en Côte d'Ivoire (UTC+0), faux dès qu'un serveur est hébergé ailleurs. Fixer `Africa/Abidjan`.

**[Majeur] Q9 — `.env.example` ne reflète pas la stack** (`DB_CONNECTION=sqlite`, `APP_LOCALE=en` vs `.env` : `fr`) ; `MAIL_MAILER=log` (aucune alerte ne part) ; `FILESYSTEM_DISK=local` pour les pièces jointes (non durable).

**[Majeur] Q10 — Pint : 115 fichiers non conformes** (`vendor/bin/pint --test`), dont `bootstrap/providers.php` (fin de ligne), 4 migrations, 8 tests, et toute la dernière vague (rapports, paie, contrats, DailyRecord, widgets, `Organisation.php`, `User.php`, `FlockService.php`). `CLAUDE.md` impose Pint avant livraison.

**[Mineur] Q11** — 30 migrations correctives sur 87 (34 % de churn, ex. 4 migrations `employees` le même jour) ; 4 migrations avec logique de données non testée ; méthodes `configure()` Filament de 300 à 376 lignes (`EmployeeForm.php:24`, `ContractsTable.php:29`, `ContractForm.php:74`) ; `Organisation.php` 568 l., `FlockService.php` 531 l. ; français codé en dur sans `lang/` (0 `__()`), acceptable en mono-langue mais fige toute extension anglophone. Aucun TODO/FIXME, aucun `dd()`.

**Bien fait** : N+1 très limité dans les tables (Filament eager-load les colonnes `relation.champ`), 3 occurrences en actions seulement (`MembersTable.php:73,82,88`) ; migration d'index dédiée (`2026_03_28_000008_add_performance_indexes.php`) ; migrations réversibles ; `QUEUE_CONNECTION=database` déjà configuré pour basculer en asynchrone.

#### Contraintes terrain Côte d'Ivoire

**[Majeur] T1 — Aucun mode hors-ligne.** Pas de service worker, pas de `manifest.json`, pas de stockage local ni de file de synchronisation (`public/`, `resources/`, `vite.config.js`). Application Livewire full-server : la saisie d'un relevé sur un site sans réseau est impossible. C'est l'écart le plus structurant pour l'usage « technicien en bâtiment ».

**[Majeur] T2 — Mobile money = énumération.** `MobileMoneyOperator.php` (Orange/MTN/Moov/Wave) et `PaymentMethod::MtnMomo` qualifient un paiement saisi à la main ; aucune intégration API (`Http::`, `api.orange`, `wave.com` : 0), aucun rapprochement automatique des encaissements.

**[Majeur] T3 — Aucun canal SMS/USSD/WhatsApp.** `PlanFeature::SmsNotifications` (`PlanFeature.php:102`) est vendu dans les plans, mais aucun provider ni `Notification` avec `via()`. Les seules alertes sont in-app (`->sendToDatabase`, `AlertExpiringStockLots.php:74-79`) : un responsable ne les voit que connecté.

**[Mineur] T4** — Filament est responsive, mais formulaires de 280 à 380 lignes (`DailyRecordForm.php:23`, `EmployeeForm.php:24`) et tables larges peu adaptés au smartphone en bâtiment ; aucune interface allégée « saisie du jour ».

**Bien fait** : `APP_LOCALE=fr` et `APP_FAKER_LOCALE=fr_FR` ; libellés, exceptions et journaux en français ; 4 opérateurs mobile money ivoiriens modélisés comme types de compte ; XOF sans décimales avec séparateur espace dans les PDF ; suivi résidence/patrouille des gardiens adapté au terrain (`Employee.php:31-32`).

---

## 4. Matrice de couverture fonctionnelle

| Domaine | Fonctionnalité attendue | État | Justification |
|---|---|---|---|
| **Bandes** | Souche, date de mise en place, effectif initial, âge à l'arrivée, fournisseur, coût d'achat | Implémenté | `Flock.php:16-33` |
| | Distinction chair / pondeuse / reproducteurs | Partiel | 3 types seedés (Pondeuse, Chair, Mixte), détection par libellé (`FlockService.php:294-307`) ; pas de reproducteurs/poussins |
| | Phases de production par type | Implémenté | `ProductionPhase.php`, défauts `Organisation.php:26-160` ; fuite inter-org (A2) |
| | Cycle planifiée → active → clôturée/annulée journalisé | Implémenté | `FlockService.php:121-227` ; statut contournable (P8) |
| | Transfert de bâtiment, bande multi-bâtiments | Absent | `Flock` = 1 `building_id` |
| **Suivi quotidien** | Mortalité + cause | Implémenté | `DailyRecord.php:19-20` ; effectif non décrémenté (P1) |
| | Réforme / tri | Absent | aucune colonne |
| | Aliment (kg), eau (L) | Implémenté | `DailyRecord.php:25-26` |
| | Poids moyen, nb pesés | Implémenté | `DailyRecord.php:27-28` |
| | Uniformité | Absent | — |
| | Température, humidité | Implémenté | `DailyRecord.php:30-32` |
| | Œufs pondus / cassés / vendus | Implémenté | `DailyRecord.php:21-23` |
| | Œufs déclassés, poids d'œuf, calibre | Absent | — |
| | Unicité 1 relevé/bande/jour | Partiel | contrainte base, pas de validation formulaire (P7) |
| | Correction rétroactive tracée | Partiel | édition/suppression libres sans trace ni recalcul |
| **Indicateurs** | Taux de mortalité | Partiel | formule dépend d'un effectif figé (P1, P10) |
| | IC / FCR | Partiel (erroné) | `FlockService.php:392-412` (P3) |
| | GMQ | Partiel | `FlockService.php:364-387`, ignore poids J0 |
| | Taux de ponte | Partiel (biaisé) | `DailyRecord.php:87-95`, `FlockService.php:417-431` (P4) |
| | Œufs par poule logée | Absent | — |
| | IPE / EPEF | Absent | — |
| | Uniformité | Absent | — |
| | Standards de souche | Absent | — |
| | Indicateurs affichés dans l'UI | Absent | `calculatePerformanceIndicators()` jamais appelé (P5) |
| | Coût par sujet | Partiel | prix d'achat / effectif courant uniquement |
| **Prophylaxie** | Programme sanitaire par type/âge, voie, dosage, délai d'attente | Implémenté | `HealthProgram.php:14-26` |
| | Génération auto du calendrier à la création de bande | Implémenté (inopérant) | `FlockService.php:78-115` ; programmes de référence jamais retournés (P2) |
| | Statuts planifiée / faite / reportée / ignorée / en retard | Implémenté | `VaccinationStatus.php`, `Vaccination.php:85-90` |
| | Traçabilité (n° lot, péremption, administrateur, coût) | Implémenté | `Vaccination.php:18,26-29` |
| | Délai d'attente appliqué (blocage vente) | Absent | P6 |
| | Lien traitement → stock médicaments | Absent | S6 |
| | Incidents sanitaires, quarantaine | Implémenté | `HealthIncident.php:17-43` |
| | Visites et annuaire vétérinaires | Implémenté | `VetVisit.php`, `Veterinarian.php` |
| | Rappels / notifications | Partiel | widget dashboard seulement (P9) |
| **Stocks** | Catégories aliment / médicaments / matériel / emballages | Implémenté | `StockCategoryType.php:11-15` ; pas de litière dédiée |
| | Articles, unités, seuil mini, emplacement | Implémenté | `Stock.php:20-36` |
| | Lots avec péremption, expiration auto | Implémenté | `StockLot.php`, `ExpireStockLots.php` |
| | Consommation FIFO/FEFO | Partiel | service existant jamais appelé (S3) |
| | Valorisation CMUP/FIFO | Absent | S4 |
| | Mouvements entrée/sortie/ajustement | Implémenté | `Stock.php:129-199` ; CRUD brut parallèle (S2) |
| | Transfert inter-fermes | Absent | enums seuls |
| | Inventaires physiques avec écart et régularisation | Implémenté | `StockInventory.php:66-76,161-208` |
| | Alerte seuil mini | Partiel | affichage sans notification |
| | Alerte péremption | Implémenté | `AlertExpiringStockLots.php` |
| | Déstockage aliment depuis le suivi quotidien | Absent | S6 |
| **Achats** | Bon de commande → approbation → réception partielle → stock | Implémenté | `StockService.php:49-117` ; lots non créés depuis l'UI (S5) |
| | Réception → dette fournisseur / facture d'achat | Absent | — |
| **Ventes** | Clients, plafond crédit, encours | Implémenté | `Customer.php:89-133`, `SaleService.php:41-55` |
| | Devis → facture | Implémenté | `SaleService.php:121-167` |
| | Facture, remise, TVA | Partiel | TVA libre défaut 0 %, pas de mentions fiscales |
| | Numérotation séquentielle unique | Implémenté (factures) / Partiel (autres) | `Sale.php:305-317` vs `count()` ailleurs (A9) |
| | Paiements partiels, multi-modes, mobile money | Implémenté | `PaymentMethod.php`, `Sale.php:240-276` ; Moov absent |
| | Avoirs | Partiel (logique erronée) | S10 |
| | Catalogue produits (œufs/plateau, poulet vif/abattu au kg) | Absent | texte libre |
| | Vente de sujets → décrément de bande | Absent | S6 |
| | Abattage, livraisons, bons de livraison | Absent | `PlanFeature::Deliveries` sans modèle |
| | PDF facture/devis en XOF et FR | Implémenté | `resources/views/pdf/commercial-document.blade.php` |
| **Finance** | Comptes caisse / banque / mobile money | Implémenté | `AccountType.php`, `MobileMoneyOperator.php` |
| | Solde stocké + historique before/after | Implémenté | `Transaction.php:21` ; concurrence (F4) |
| | Transferts inter-comptes avec frais | Implémenté | `AccountTransfer.php:126-221` |
| | Contrepassation | Implémenté | `TransactionService.php:57-93` ; suppression physique aussi possible (F1) |
| | Verrouillage de période / clôture | Absent | — |
| | Rapprochement bancaire | Partiel | flag + recalcul, sans relevé |
| | Dépenses avec justificatif, imputation ferme/bande/fournisseur | Implémenté | `Expense.php`, `ExpenseForm.php:116-148` ; justificatif public (X10) |
| | Circuit d'approbation des dépenses | Absent | — |
| | Vente encaissée → transaction | Implémenté | `Sale.php:259-270` |
| | Paie payée → transaction / dépense | Absent | F3 |
| | Coût de revient et marge par bande | Absent | F8 |
| | Plan comptable SYSCOHADA, journaux, partie double, exercice | Absent | F7 |
| | TVA 18 % collectée / déductible | Partiel | libre sur ventes, absente sur dépenses |
| | Export comptable (Sage, FEC) | Absent | — |
| | XOF, formatage FR | Implémenté | hétérogène (R5) |
| **RH / paie** | Employés (identité, CNPS, pièce d'identité, mobile money) | Implémenté | `Employee.php:20-34` |
| | Contrats CDI/CDD/saisonnier, avenants | Implémenté | `ContractType.php`, `ContractAmendment.php` |
| | Journaliers | Partiel | enum orphelin, paie mensuelle seule |
| | Présences | Implémenté | `Attendance.php` |
| | Congés (demande, approbation) | Implémenté | `Leave.php:101-123` ; règles CI non conformes (F13) |
| | Paie brut → CNPS → ITS → net | Partiel (erreurs) | F2, F6, F11 |
| | Paie ↔ présences | Absent | `worked_days` manuel |
| | Bulletin PDF | Absent | feature vendue sans vue |
| | Déclarations CNPS / ITS | Absent | — |
| **Reporting** | Rapports production / commercial / financier / stocks | Implémenté | `Pages/Reports/*` |
| | Exactitude des KPI | Partiel | tronqués à 200 (F5, S12), sans % ni IC |
| | Consolidation et comparaison inter-fermes | Absent | R1 |
| | Export Excel / PDF | Implémenté | synchrone (R3) |
| **Multi-fermes** | Isolation inter-organisations | Implémenté | scope tenant systématique |
| | Isolation intra-organisation par ferme | Partiel | 9 ressources sur 37 (X2) |
| | Sélecteur de ferme global | Implémenté | `CurrentFarm.php`, `FarmSelector` |
| **Terrain CI** | Mode hors-ligne / synchronisation | Absent | T1 |
| | Intégration mobile money (API) | Absent | T2 |
| | SMS / USSD / WhatsApp | Absent | T3 |
| | Interface mobile de saisie | Partiel | responsive Filament seulement |
| | Langue française | Implémenté | libellés en dur |
| **Sécurité** | Rôles et permissions par organisation | Implémenté | trait sur 37 ressources |
| | 2FA, reset mot de passe | Absent | X7 |
| | Journal d'activité / piste d'audit | Absent | X13 |
| | Sauvegardes, supervision | Absent | Q7 |

---

## 5. Risques principaux, classés par impact

1. **Chiffres d'élevage faux dès le premier jour** (P1, P3, P4, P5, P10) : l'effectif ne bouge pas, les indicateurs ne sont pas affichés et ceux calculés sont incorrects. Le produit ne remplit pas sa fonction première de pilotage technique. Décisions d'abattage, de réforme et de commande d'aliment prises sur de mauvaises bases.
2. **Trésorerie et stocks non fiables** (F1, F4, S2, S7, S9, S10, A7) : écritures modifiables et supprimables, soldes écrasés en concurrence, mouvements fantômes, avoirs débitant la caisse. Détournements indétectables, écarts d'inventaire inexplicables, refus par un expert-comptable.
3. **Fuite de données entre fermes d'un même groupe** (X2, X3, X10) : salaires, comptes bancaires, factures et justificatifs médicaux visibles par des employés d'un autre site ou par URL publique. Conflits internes, exposition de données personnelles et de santé.
4. **Auto-escalade d'un administrateur vers propriétaire** (X4) : perte de contrôle du groupe sur ses propres organisations.
5. **Paie non conforme** (F2, F6, F13) : plafond CNPS faux d'un facteur 12, barème ITS non sourcé, congés hors Code du travail. Redressements CNPS/DGI et contentieux salariés.
6. **Crash en production PostgreSQL sur les équipements** (A1) et **saisie de factures/bons de commande probablement bloquée** (S1) : fonctionnalités annoncées inutilisables.
7. **Prophylaxie non planifiée** (P2, P6, P9) : calendrier vaccinal vide, délai d'attente ignoré, aucun rappel. Risque sanitaire et réglementaire.
8. **Absence de filet technique** (Q1, Q2, Q3, Q6, Q7) : tests rouges et non déterministes, CI inexécutable, pas de git, pas de sauvegarde. Toute correction risque d'en casser une autre sans que personne ne le voie ; perte de données possible.
9. **Inadéquation au terrain** (T1, T2, T3) : sans hors-ligne ni SMS, l'outil reste un back-office de siège ; les fonctionnalités vendues dans les plans n'existent pas.
10. **Dette structurelle** (A5, A8, A9, A12, F7, R1) : trois stratégies de discriminant, journaux cascadés, numérotation par `count()`, logique dans les modèles, pas de comptabilité générale ni de consolidation. Coût croissant de chaque évolution.

---

## 6. Plan d'action priorisé

Estimations en jours-personne pour un développeur Laravel/Filament confirmé connaissant le dépôt. Les références renvoient aux constats de la section 3.

### 6.1 Quick wins (< 1 jour chacun)

| # | Action | Constats | Effort |
|---|---|---|---|
| 1 | Initialiser git, premier commit, `.gitignore` vérifié ; ajouter `memory_limit=1G` dans `phpunit.xml` ; retirer le mot de passe de `phpunit.xml` (variable d'environnement) | Q7, Q1, X12 | 0,25 j |
| 2 | Corriger `FlockFactory` (`supplier_name`, `is_active`) et le test fournisseur orphelin ; relancer la suite | Q2 | 0,5 j |
| 3 | `vendor/bin/pint` sur tout le projet | Q10 | 0,1 j |
| 4 | `timezone = Africa/Abidjan` ; aligner `.env.example` (pgsql, `fr`) ; déclarer `openspout/openspout` dans `composer.json` | Q8, Q9, R5 | 0,25 j |
| 5 | Brancher `CreateDailyRecord::handleRecordCreation` sur `FlockService::recordDailyData` (correctif provisoire avant la refonte de l'effectif) ; `Rule::unique` sur `flock_id + record_date` | P1, P7 | 0,5 j |
| 6 | `HealthProgram::getProgramForFlock/getUpcomingTreatmentsForFlock` : `orWhereNull('organisation_id')` | P2 | 0,25 j |
| 7 | `Flock::getCurrentPhase()` et `ProductionPhase::getPhaseForAge()` : filtre sur l'organisation de la bande | A2 | 0,25 j |
| 8 | Aligner les enums équipements sur les CHECK (`in_stock` → `in_service`, `in_maintenance` → `under_maintenance`, `exit` → `decommission`, `broken` → `damaged`, retirer ou ajouter `cleaning/calibration/replacement`) ; test d'insertion de chaque `case` | A1 | 0,5 j |
| 9 | Retirer `EditAction`/`DeleteAction`/`DeleteBulkAction` de Transactions, Payments, StockMovements, StockLots, AccountTransfers ; supprimer les pages `create` de StockMovements et StockLots ; `canEdit/canDelete = false` | F1, S2, S7 | 0,5 j |
| 10 | Désactiver le `Select status` en édition sur Flock, Sale, Expense ; statut piloté par les actions | P8, S8, F9 | 0,5 j |
| 11 | Justificatifs dépenses et congés : disque privé + action de téléchargement authentifiée ; trancher le disque des pièces d'identité | X10, X11 | 0,75 j |
| 12 | `MemberForm` : rôles proposés bornés à `weight < acteur` ; `OrganisationRoleForm` : `weight` borné ; rôles `is_system` non éditables sauf owner | X4 | 0,5 j |
| 13 | `Password::defaults()` dans `MemberForm` ; `->passwordReset()` sur les panels | X8, X7 (partiel) | 0,25 j |
| 14 | `EmployeeForm` : `user_id` filtré sur les membres de l'organisation | X1 | 0,1 j |
| 15 | Ajouter `canAccess()` aux 4 pages de rapport et `canView()` aux widgets, adossés aux permissions existantes | X3 | 0,5 j |
| 16 | `EmployeeService::log` : catégorie valide ; `FarmLogCategory::Hr` | F13 | 0,1 j |
| 17 | `PaymentMethod` : ajouter Moov Money ; actions finance/ventes : `canPerformAction('<slug seedé>')` | S13, X5 | 0,5 j |

Total quick wins : environ 6,5 j.

### 6.2 Court terme (< 1 mois)

| # | Action | Constats | Effort |
|---|---|---|---|
| 18 | **Cloisonnement par ferme généralisé** : `accessibleFarmIds()` dans `ScopesToMemberFarms`, appliqué aux 28 ressources restantes, aux enfants via relation parente, aux Select de comptes/employés, aux widgets et rapports ; un test Pest « membre restreint » par ressource | X2, X3, Q4 | 5 j |
| 19 | **Effectif dérivé** : `quantity_current` calculé depuis `quantity_started − Σ mortality − Σ culls − Σ ventes` ; colonnes `culls`, `birds_present` sur `daily_records` ; recalcul sur édition/suppression ; incident sanitaire référençant les relevés | P1, P10, P11 | 3 j |
| 20 | **Indicateurs corrects et visibles** : IC, GMQ, taux de mortalité et de ponte, œufs par poule logée, IPE ; section « Performances » dans `FlockInfolist` ; KPI en pourcentage dans le rapport production ; colonne `production_kind` (broiler/layer/breeder) sur `chicken_types` ; tests Pest avec datasets | P3, P4, P5, P11, Q3 | 4 j |
| 21 | **Délai d'attente** : `withdrawal_until` sur `vaccinations`, garde-fou vente et clôture de bande | P6 | 1 j |
| 22 | **Verrous et transactions** : `lockForUpdate()` dans `Account::credit/debit`, `Stock::recordIn/recordOut`, `consumeFromLots`, `Sale::addPayment` ; `credit/debit` acceptant une date ; `AccountTransfer::complete` verrouillé dans la transaction | F4, F10, S9 | 2 j |
| 23 | **Numérotation** : table `sequences` verrouillée, utilisée par les 12 générateurs | A9 | 1,5 j |
| 24 | **Ventes/achats** : corriger le Repeater `items` (test Livewire `CreateSale`/`CreatePurchaseOrder`) ; réception par ligne avec quantité, n° lot, péremption ; branchement FIFO sur les sorties ; CMUP à la réception ; solde client filtré sur les ventes actives ; avoir en deux cas (créance vs remboursement) | S1, S3, S4, S5, S10, S11 | 5 j |
| 25 | **Paie** : plafond CNPS 3 375 000, branches employeur, barème ITS versionné en table avec source et date d'effet, parts fiscales, `other_deductions` dans le net, `markAsPaid` créant dépense + transaction, bulletin PDF ; validation par un expert-comptable ; tests datasets | F2, F3, F6, F11, F12 | 5 j |
| 26 | **Notifications** : commande planifiée vaccinations en retard, relevés manquants, factures échues, stock bas ; canal e-mail réel ; export PDF/XLSX en jobs | P9, R3, T3 (partiel) | 3 j |
| 27 | **Rapports** : agrégats SQL (`SUM/AVG/GROUP BY`), suppression des `limit(200)`, filtre et colonne ferme, comparaison inter-fermes | F5, S12, R1, R2 | 4 j |
| 28 | **CI** : service PostgreSQL, PHP 8.3, `pint --test`, retrait Flux ; README d'installation ; `spatie/laravel-backup` + planification ; Sentry ou équivalent | Q6, Q7 | 2 j |
| 29 | **Piste d'audit** : `spatie/laravel-activitylog` sur transactions, paiements, mouvements, relevés, paies, bandes ; SoftDeletes ou `restrictOnDelete` sur les tables de faits ; correction rétroactive de relevé bornée dans le temps avec motif | X13, A7, A8 | 3 j |
| 30 | **2FA** owner/admin (`->multiFactorAuthentication`) | X7 | 1 j |
| 31 | Squash des migrations (`schema:dump --prune`) après stabilisation ; retrait du support SQLite | A3, A4, Q11 | 1 j |

Total court terme : environ 40 j (2 développeurs sur 1 mois).

### 6.3 Refonte structurelle (1 à 3 mois)

| # | Action | Constats | Effort |
|---|---|---|---|
| 32 | **Discriminant unique** : `organisation_id NOT NULL` (+ `farm_id`) sur toutes les tables de faits, rempli par un trait `BelongsToOrganisation` avec validation croisée ferme/organisation ; index composites `(organisation_id, date)` ; FK composites ; réécriture des scopes sans `whereHas` à 3 niveaux | A5, A6, A11, X2 | 8 j |
| 33 | **Coût de revient et marge par bande** : agrégation poussins + aliment (mouvements de stock) + médicaments + vétérinaire + main-d'œuvre (paie ventilée) + charges indirectes (clé de répartition) ; marge = ventes imputées − coûts ; tableau de bord par bande et par ferme | F8 | 8 j |
| 34 | **Chaîne stock ↔ élevage ↔ ventes** : déstockage aliment depuis le relevé, déstockage médicaments depuis vaccinations/traitements, vente de sujets décrémentant la bande, référentiel produits (œufs par plateau/alvéole, poulet vif/abattu au kg), livraisons et abattage | S6, S13 | 10 j |
| 35 | **Comptabilité SYSCOHADA minimale** : plan comptable paramétrable, mapping catégorie → compte, journal des écritures générées automatiquement (ventes, encaissements, dépenses, paie, amortissements simples), TVA collectée/déductible à 18 %, exercice et clôture, export Excel/CSV compatible Sage ; mentions fiscales sur les PDF | F7 | 15 j |
| 36 | **Refactoring domaine** : modèles réduits à état/relations/scopes, règles dans les services, suppression de la duplication `calculateTotals`, recalcul unique après persistance des lignes, `$fillable` sans colonnes dérivées, factories pour les 28 modèles manquants | A12, A13, A14 | 8 j |
| 37 | **Terrain** : PWA de saisie journalière hors-ligne (relevé, mortalité, aliment, œufs, vaccination faite) avec file de synchronisation et résolution de conflits ; interface mobile allégée | T1, T4 | 20 j |
| 38 | **SMS et mobile money** : passerelle SMS (Orange/MTN ou agrégateur), notifications `via(['database','sms'])` ; intégration API Wave/Orange Money/MTN MoMo pour rapprocher les encaissements clients | T2, T3 | 10 j |
| 39 | **Consolidation groupe** : tableau de bord direction multi-fermes (indicateurs techniques, trésorerie, marges par site), comparaisons inter-sites, période et devise ; agrégats pré-calculés quotidiens | R1 | 8 j |

Total refonte : environ 87 j.

### 6.4 Ordre recommandé

1. Semaine 1 : quick wins 1 à 17 (rendre les chiffres justes et fermer les fuites les plus graves), suite de tests verte.
2. Semaines 2 à 5 : actions 18 à 31, en commençant par le cloisonnement (18), l'effectif dérivé (19), les verrous (22) et la paie (25).
3. Ensuite : actions 32 (discriminant unique) puis 33 et 34 (coût de revient, chaîne stock/élevage), avant toute ouverture commerciale multi-sites ; 35 et 37 selon la priorité entre exigence comptable et usage terrain.

---

## Annexe — Points non conclus

- **S1 (Repeater `items`)** : conclusion fondée sur la lecture de `vendor/filament/forms/src/Components/Repeater.php:1013` ; non reproduit à l'exécution.
- **F6 (barème ITS)** : le code ne cite ni source ni date ; impossible de déterminer quelle version du barème est visée.
- **A3 (`employment_type`)** : la présence réelle de la colonne en dev/prod dépend de l'historique d'exécution des migrations ; à vérifier avec `\d employees`.
- **Q2 (rôles système en test)** : la cause exacte de l'échec intermittent de `addMember` (ordre du seed des permissions ?) n'a pas été isolée.
- Les résultats de tests ont été obtenus avec `php -d memory_limit=2G vendor/bin/pest --compact` ; la commande du projet plante avant la fin.
