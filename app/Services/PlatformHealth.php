<?php

namespace App\Services;

use App\Console\Commands\Doctor;
use App\Jobs\GenerateReportExport;
use App\Models\OrganisationRole;
use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Contrôles d'exploitation peu coûteux, affichés sur le tableau de bord Admin : planificateur,
 * file d'attente, sauvegardes, supervision, courriel, SMS, exports, double authentification.
 * Le diagnostic complet (`gestavi:doctor`) reste la référence ; ici, rien qui lance un processus
 * externe ni ne sonde le réseau.
 */
class PlatformHealth
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARN = 'warn';

    public const STATUS_FAIL = 'fail';

    /**
     * @return list<array{check: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->scheduler(),
            $this->queue(),
            $this->failedJobs(),
            $this->backups(),
            $this->monitoring(),
            $this->mail(),
            $this->sms(),
            $this->exportsDisk(),
            $this->twoFactor(),
        ];
    }

    /**
     * @return array{ok: int, warn: int, fail: int}
     */
    public function summary(): array
    {
        $checks = collect($this->checks());

        return [
            'ok' => $checks->where('status', self::STATUS_OK)->count(),
            'warn' => $checks->where('status', self::STATUS_WARN)->count(),
            'fail' => $checks->where('status', self::STATUS_FAIL)->count(),
        ];
    }

    /** @return array{check: string, status: string, detail: string} */
    private function scheduler(): array
    {
        $last = Cache::get(Doctor::SCHEDULER_HEARTBEAT_KEY);
        $lastAt = $last ? Carbon::parse($last) : null;

        if ($lastAt && $lastAt->diffInMinutes(now()) <= 10) {
            return $this->row('Planificateur', self::STATUS_OK, 'dernier battement '.$lastAt->diffForHumans());
        }

        return $this->row('Planificateur', $this->strict() ? self::STATUS_FAIL : self::STATUS_WARN, $lastAt
            ? 'muet depuis '.$lastAt->diffForHumans().' : météo, prévisions, alertes, abonnements et sauvegardes sont à l\'arrêt.'
            : 'aucun battement : le cron « schedule:run » ne tourne pas.');
    }

    /** @return array{check: string, status: string, detail: string} */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        return $connection === 'sync'
            ? $this->row('File d\'attente', $this->strict() ? self::STATUS_FAIL : self::STATUS_WARN, 'synchrone : exports et notifications bloquent les requêtes.')
            : $this->row('File d\'attente', self::STATUS_OK, $connection);
    }

    /** @return array{check: string, status: string, detail: string} */
    private function failedJobs(): array
    {
        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return $this->row('Tâches en échec', self::STATUS_WARN, 'table failed_jobs introuvable.');
        }

        return $failed > 0
            ? $this->row('Tâches en échec', self::STATUS_WARN, $failed.' tâche(s) en échec : queue:failed puis queue:retry.')
            : $this->row('Tâches en échec', self::STATUS_OK, 'aucune');
    }

    /** @return array{check: string, status: string, detail: string} */
    private function backups(): array
    {
        $disks = (array) config('backup.backup.destination.disks', []);
        $folder = (string) config('backup.backup.name');

        if ($disks === []) {
            return $this->row('Sauvegardes', self::STATUS_FAIL, 'aucun disque de destination configuré.');
        }

        $newest = null;

        foreach ($disks as $disk) {
            try {
                $storage = Storage::disk($disk);
                $last = collect($storage->files($folder))
                    ->filter(fn (string $f): bool => Str::endsWith($f, '.zip'))
                    ->map(fn (string $f): int => $storage->lastModified($f))
                    ->max();
                $newest = max($newest ?? 0, (int) $last) ?: $newest;
            } catch (Throwable $e) {
                return $this->row('Sauvegardes', self::STATUS_FAIL, "disque {$disk} injoignable : ".Str::limit($e->getMessage(), 80));
            }
        }

        if (! $newest) {
            return $this->row('Sauvegardes', $this->strict() ? self::STATUS_FAIL : self::STATUS_WARN, 'aucune sauvegarde sur '.implode(', ', $disks).'.');
        }

        $age = Carbon::createFromTimestamp($newest);
        $tooOld = $age->diffInHours(now()) > 36;

        return $this->row('Sauvegardes', $tooOld ? self::STATUS_WARN : self::STATUS_OK, 'dernière '.$age->diffForHumans().' ('.implode(', ', $disks).')'.(in_array('local', $disks, true) && count($disks) === 1 ? ' — uniquement sur le serveur' : ''));
    }

    /** @return array{check: string, status: string, detail: string} */
    private function monitoring(): array
    {
        return blank(config('sentry.dsn'))
            ? $this->row('Supervision des erreurs', self::STATUS_WARN, 'Sentry non configuré : les erreurs ne remontent nulle part.')
            : $this->row('Supervision des erreurs', self::STATUS_OK, 'Sentry configuré');
    }

    /** @return array{check: string, status: string, detail: string} */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        return $mailer === 'log'
            ? $this->row('Courriel', $this->strict() ? self::STATUS_FAIL : self::STATUS_WARN, 'mode journal : alertes et réinitialisations de mot de passe ne partent pas.')
            : $this->row('Courriel', self::STATUS_OK, $mailer);
    }

    /** @return array{check: string, status: string, detail: string} */
    private function sms(): array
    {
        $driver = (string) config('sms.default');

        return $driver === 'log'
            ? $this->row('SMS', self::STATUS_WARN, 'mode journal : les SMS sont journalisés, pas envoyés.')
            : $this->row('SMS', self::STATUS_OK, $driver);
    }

    /** @return array{check: string, status: string, detail: string} */
    private function exportsDisk(): array
    {
        $disk = GenerateReportExport::disk();

        return $disk === 'local'
            ? $this->row('Exports de rapports', $this->strict() ? self::STATUS_WARN : self::STATUS_OK, 'disque local (liens signés) ; viser MinIO en production.')
            : $this->row('Exports de rapports', self::STATUS_OK, $disk);
    }

    /** @return array{check: string, status: string, detail: string} */
    private function twoFactor(): array
    {
        $fullAccess = OrganisationUser::query()
            ->whereHas('organisationRole', fn ($q) => $q->where('weight', '>=', OrganisationRole::FULL_ACCESS_WEIGHT))
            ->select('user_id');

        $missing = User::query()
            ->where(fn ($query) => $query->whereHas('platformRoles')->orWhereIn('id', $fullAccess))
            ->whereNull('app_authentication_secret')
            ->count();

        return $missing > 0
            ? $this->row('Double authentification', self::STATUS_WARN, $missing.' compte(s) à privilèges sans 2FA (demandée à leur prochaine connexion).')
            : $this->row('Double authentification', self::STATUS_OK, 'tous les comptes à privilèges sont protégés');
    }

    private function strict(): bool
    {
        return ! app()->environment(['local', 'testing']);
    }

    /** @return array{check: string, status: string, detail: string} */
    private function row(string $check, string $status, string $detail): array
    {
        return ['check' => $check, 'status' => $status, 'detail' => $detail];
    }
}
