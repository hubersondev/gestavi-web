<?php

namespace App\Console\Commands;

use App\Enums\MobileMoneyOperator;
use App\Models\JournalEntry;
use App\Models\LedgerAnchor;
use App\Models\Organisation;
use App\Models\OrganisationRole;
use App\Models\OrganisationUser;
use App\Models\Product;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Diagnostic d'exploitation : vérifie en une commande ce qu'un environnement de
 * recette ou de production doit avoir en place (base, migrations, file d'attente,
 * planificateur, sauvegardes, ledger, supervision, 2FA, organisations à niveau).
 * Code de sortie 1 dès qu'un contrôle est en échec.
 */
class Doctor extends Command
{
    public const string SCHEDULER_HEARTBEAT_KEY = 'gestavi.scheduler.heartbeat';

    private const int SCHEDULER_MAX_SILENCE_MINUTES = 10;

    private const int BACKUP_MAX_AGE_HOURS = 36;

    private const int ANCHOR_MAX_AGE_DAYS = 2;

    protected $signature = 'gestavi:doctor';

    protected $description = 'Diagnostic d\'exploitation : base, migrations, file d\'attente, planificateur, sauvegardes, ledger, supervision, sécurité, organisations';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    private bool $strict = false;

    public function handle(LedgerService $ledger, Migrator $migrator): int
    {
        $environment = (string) config('app.env');
        $this->strict = ! in_array($environment, ['local', 'testing'], true);

        $this->checkApplication($environment);
        $this->checkDatabase($migrator);
        $this->checkQueue();
        $this->checkScheduler();
        $this->checkBackups();
        $this->checkLedger($ledger);
        $this->checkMonitoring();
        $this->checkSecurity();
        $this->checkTenants();
        $this->checkStorage();

        $this->table(['État', 'Contrôle', 'Détail'], array_map(fn (array $r): array => [$r['status'], $r['check'], $r['detail']], $this->results));

        $failures = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'FAIL'));
        $warnings = count(array_filter($this->results, fn (array $r): bool => $r['status'] === 'WARN'));

        $this->line(sprintf('%d contrôle(s), %d en échec, %d avertissement(s) — environnement %s%s.', count($this->results), $failures, $warnings, $environment, $this->strict ? ' (règles strictes)' : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function pass(string $check, string $detail = ''): void
    {
        $this->results[] = ['status' => 'OK', 'check' => $check, 'detail' => $detail];
    }

    private function caution(string $check, string $detail): void
    {
        $this->results[] = ['status' => 'WARN', 'check' => $check, 'detail' => $detail];
    }

    private function failure(string $check, string $detail): void
    {
        $this->results[] = ['status' => 'FAIL', 'check' => $check, 'detail' => $detail];
    }

    /**
     * Échec hors développement, avertissement en local : le contrôle compte, mais
     * un poste de développement n'a pas à le satisfaire.
     */
    private function failIfStrict(string $check, string $detail): void
    {
        $this->strict ? $this->failure($check, $detail) : $this->caution($check, $detail);
    }

    private function checkApplication(string $environment): void
    {
        if ((bool) config('app.debug') && $this->strict) {
            $this->failure('Mode debug', "APP_DEBUG=true en environnement {$environment} : fuite de configuration et de traces aux utilisateurs.");
        } else {
            $this->pass('Mode debug', (bool) config('app.debug') ? 'actif (toléré en développement)' : 'inactif');
        }

        if (blank(config('app.key'))) {
            $this->failure('Clé applicative', 'APP_KEY absente : lancer php artisan key:generate.');
        } else {
            $this->pass('Clé applicative');
        }

        $url = (string) config('app.url');
        if (! Str::startsWith($url, 'https://')) {
            $this->failIfStrict('URL applicative', "APP_URL={$url} : HTTPS attendu (cookies de session, webhooks signés, PWA).");
        } else {
            $this->pass('URL applicative', $url);
        }

        config('app.timezone') === 'Africa/Abidjan'
            ? $this->pass('Fuseau horaire', 'Africa/Abidjan')
            : $this->caution('Fuseau horaire', config('app.timezone').' : Africa/Abidjan attendu pour les relevés et la paie.');
    }

    private function checkDatabase(Migrator $migrator): void
    {
        try {
            DB::select('select 1');
            $this->pass('Base de données', config('database.default').' / '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $this->failure('Base de données', 'Connexion impossible : '.Str::limit($e->getMessage(), 120));

            return;
        }

        if (! $migrator->repositoryExists()) {
            $this->failure('Migrations', 'Table des migrations absente : lancer php artisan migrate.');

            return;
        }

        $ran = $migrator->getRepository()->getRan();
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);

        $pending === []
            ? $this->pass('Migrations', count($ran).' appliquées, aucune en attente')
            : $this->failure('Migrations', count($pending).' en attente : '.implode(', ', array_slice($pending, 0, 3)).(count($pending) > 3 ? '…' : ''));
    }

    private function checkQueue(): void
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            $this->failIfStrict('File d\'attente', 'QUEUE_CONNECTION=sync : exports, notifications et SMS bloqueraient les requêtes. Utiliser database ou redis avec un worker.');
        } else {
            $this->pass('File d\'attente', $connection);
        }

        try {
            $failed = DB::table('failed_jobs')->count();
            $failed > 0
                ? $this->caution('Jobs en échec', "{$failed} job(s) en échec : php artisan queue:failed, puis queue:retry.")
                : $this->pass('Jobs en échec', 'aucun');
        } catch (Throwable) {
            $this->caution('Jobs en échec', 'table failed_jobs introuvable.');
        }
    }

    private function checkScheduler(): void
    {
        $last = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);
        $lastAt = $last ? Carbon::parse($last) : null;

        if ($lastAt && $lastAt->diffInMinutes(now()) <= self::SCHEDULER_MAX_SILENCE_MINUTES) {
            $this->pass('Planificateur', 'dernier battement '.$lastAt->diffForHumans());
        } else {
            $this->failIfStrict('Planificateur', $lastAt
                ? 'muet depuis '.$lastAt->diffForHumans().' : vérifier la ligne cron « php artisan schedule:run » ou le service schedule:work.'
                : 'aucun battement enregistré : le cron « * * * * * php artisan schedule:run » ne tourne pas.');
        }
    }

    private function checkBackups(): void
    {
        $disks = (array) config('backup.backup.destination.disks', []);
        $folder = (string) config('backup.backup.name');

        foreach ($disks as $disk) {
            try {
                $storage = Storage::disk($disk);
                $probe = $folder.'/.gestavi-doctor-'.Str::random(8);
                $storage->put($probe, 'ok');
                $storage->delete($probe);
            } catch (Throwable $e) {
                $this->failure("Sauvegardes ({$disk})", 'disque non inscriptible : '.Str::limit($e->getMessage(), 100));

                continue;
            }

            $newest = collect($storage->files($folder))
                ->filter(fn (string $f): bool => Str::endsWith($f, '.zip'))
                ->map(fn (string $f): int => $storage->lastModified($f))
                ->max();

            if ($newest === null) {
                $this->failIfStrict("Sauvegardes ({$disk})", 'aucune sauvegarde : lancer php artisan backup:run --only-db.');
            } elseif (Carbon::createFromTimestamp($newest)->diffInHours(now()) > self::BACKUP_MAX_AGE_HOURS) {
                $this->failIfStrict("Sauvegardes ({$disk})", 'dernière sauvegarde '.Carbon::createFromTimestamp($newest)->diffForHumans().' : la planification quotidienne ne tourne pas.');
            } else {
                $this->pass("Sauvegardes ({$disk})", 'dernière sauvegarde '.Carbon::createFromTimestamp($newest)->diffForHumans());
            }

            if ($disk === 'local' && $this->strict) {
                $this->caution('Sauvegardes hors site', 'seul le disque local est configuré : une sauvegarde sur le serveur ne protège pas d\'une perte du serveur.');
            }
        }

        if ($disks === []) {
            $this->failure('Sauvegardes', 'aucun disque de destination configuré (config/backup.php).');
        }

        $binary = rtrim((string) config('database.connections.pgsql.dump.dump_binary_path', ''), '/\\');
        $command = $binary !== '' ? $binary.DIRECTORY_SEPARATOR.'pg_dump' : 'pg_dump';
        $process = Process::fromShellCommandline(escapeshellarg($command).' --version');
        $process->run();

        $process->isSuccessful()
            ? $this->pass('pg_dump', trim($process->getOutput()))
            : $this->failIfStrict('pg_dump', 'introuvable : renseigner DB_DUMP_BINARY_PATH ou ajouter PostgreSQL au PATH.');
    }

    private function checkLedger(LedgerService $ledger): void
    {
        $disk = (string) config('ledger.anchor_disk', 'local');

        $disk === 'local'
            ? $this->caution('Ancrage du ledger', 'LEDGER_ANCHOR_DISK=local : la preuve d\'intégrité reste sur le serveur de la base ; viser un disque distant (S3, SFTP).')
            : $this->pass('Ancrage du ledger', "disque {$disk}");

        $organisations = Organisation::query()->whereIn('id', JournalEntry::query()->select('organisation_id'))->get();

        if ($organisations->isEmpty()) {
            $this->pass('Chaîne du ledger', 'aucune écriture comptable encore enregistrée');

            return;
        }

        foreach ($organisations as $organisation) {
            $verification = $ledger->verify($organisation->id);

            if (! $verification['valid']) {
                $this->failure('Chaîne du ledger', "{$organisation->name} : rupture sur {$verification['broken_entry_number']} ({$verification['reason']}).");

                continue;
            }

            $lastAnchor = LedgerAnchor::query()->where('organisation_id', $organisation->id)->latest('id')->first();

            if (! $lastAnchor || $lastAnchor->created_at->diffInDays(now()) > self::ANCHOR_MAX_AGE_DAYS) {
                $this->caution('Chaîne du ledger', "{$organisation->name} : {$verification['checked']} écriture(s) intègre(s) mais ".($lastAnchor ? 'dernier ancrage '.$lastAnchor->created_at->diffForHumans() : 'aucun ancrage').' ; gestavi:ledger-anchor est planifié à 23 h 45.');
            } else {
                $this->pass('Chaîne du ledger', "{$organisation->name} : {$verification['checked']} écriture(s), ancrée ".$lastAnchor->created_at->diffForHumans());
            }
        }
    }

    private function checkMonitoring(): void
    {
        blank(config('sentry.dsn'))
            ? $this->caution('Supervision des erreurs', 'SENTRY_LARAVEL_DSN vide : les erreurs ne remontent nulle part.')
            : $this->pass('Supervision des erreurs', 'Sentry configuré');

        $mailer = (string) config('mail.default');
        $mailer === 'log'
            ? $this->failIfStrict('Courriel', 'MAIL_MAILER=log : alertes, réinitialisations de mot de passe et rapports de sauvegarde ne partent pas.')
            : $this->pass('Courriel', $mailer);

        $sms = (string) config('sms.default');
        $sms === 'log'
            ? $this->caution('SMS', 'SMS_DRIVER=log : les SMS sont journalisés, pas envoyés.')
            : $this->pass('SMS', $sms);

        $missing = collect(MobileMoneyOperator::cases())
            ->filter(fn (MobileMoneyOperator $operator): bool => blank(config("mobilemoney.operators.{$operator->value}.webhook_secret")))
            ->map(fn (MobileMoneyOperator $operator): string => $operator->value);

        $missing->isEmpty()
            ? $this->pass('Mobile money', 'secrets de webhook configurés pour les quatre opérateurs')
            : $this->caution('Mobile money', 'webhooks refusés (secret absent) pour : '.$missing->implode(', ').'.');
    }

    private function checkSecurity(): void
    {
        $fullAccessMembers = OrganisationUser::query()
            ->whereHas('organisationRole', fn ($q) => $q->where('weight', '>=', OrganisationRole::FULL_ACCESS_WEIGHT))
            ->select('user_id');

        $privileged = User::query()
            ->where(fn ($query) => $query->whereHas('platformRoles')->orWhereIn('id', $fullAccessMembers))
            ->get()
            ->reject(fn (User $user): bool => $user->hasTwoFactorEnabled());

        $privileged->isEmpty()
            ? $this->pass('Double authentification', 'tous les comptes à privilèges sont protégés')
            : $this->caution('Double authentification', $privileged->count().' compte(s) à privilèges sans 2FA (la configuration leur sera demandée à la connexion) : '.$privileged->pluck('email')->take(5)->implode(', '));
    }

    private function checkTenants(): void
    {
        $outdated = Organisation::query()->get()->filter(function (Organisation $organisation): bool {
            $roles = $organisation->organisationRoles()->where('is_system', true)->count();
            $products = Product::query()->where('organisation_id', $organisation->id)->count();

            return $roles < count(Organisation::SYSTEM_ROLES) || $products < count(Product::DEFAULT_PRODUCTS);
        });

        $outdated->isEmpty()
            ? $this->pass('Organisations', Organisation::query()->count().' organisation(s) au niveau du code')
            : $this->caution('Organisations', $outdated->count().' organisation(s) à remettre à niveau (rôles ou produits manquants) : php artisan gestavi:tenants-upgrade.');
    }

    private function checkStorage(): void
    {
        foreach ([storage_path('app'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            is_writable($path)
                ? $this->pass('Dossier inscriptible', $path)
                : $this->failure('Dossier inscriptible', "{$path} n'est pas inscriptible par l'utilisateur du serveur web.");
        }

        is_link(public_path('storage')) || is_dir(public_path('storage'))
            ? $this->pass('Lien public/storage')
            : $this->caution('Lien public/storage', 'absent : php artisan storage:link (logos, justificatifs).');
    }
}
