<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * « Se connecter en tant que » pour le support : un administrateur de la plateforme ouvre
 * l'espace d'un utilisateur ferme tel que celui-ci le voit, puis revient à son propre compte.
 * Le début et la fin sont journalisés dans le journal d'audit, et chaque écriture faite pendant
 * la session porte l'identifiant de l'administrateur (LogsAuditTrail).
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function canImpersonate(User $admin, User $target): bool
    {
        return $admin->isPlatformUser()
            && $admin->hasPermission('users.update')
            && ! $target->isPlatformUser()
            && (bool) $target->is_active
            && $admin->isNot($target);
    }

    public static function isActive(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function impersonator(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::query()->find($id) : null;
    }

    /**
     * Bascule la session sur l'utilisateur cible. Retourne l'URL d'arrivée (panel Ferme).
     */
    public static function start(User $admin, User $target): string
    {
        if (! self::canImpersonate($admin, $target)) {
            abort(403, 'Vous ne pouvez pas vous connecter en tant que cet utilisateur.');
        }

        activity('gestavi')
            ->causedBy($admin)
            ->performedOn($target)
            ->event('impersonation_started')
            ->withProperties(['attributes' => ['email' => $target->email]])
            ->log('Connexion en tant que '.$target->name);

        Session::put(self::SESSION_KEY, $admin->getKey());
        Auth::login($target);
        Session::regenerate();
        // La régénération conserve les données : on réaffirme la clé par sécurité
        Session::put(self::SESSION_KEY, $admin->getKey());

        return url('/farm');
    }

    /**
     * Revient au compte de l'administrateur. Retourne l'URL de retour (fiche de l'utilisateur),
     * ou null si aucune session de support n'est ouverte.
     */
    public static function leave(): ?string
    {
        $admin = self::impersonator();

        if (! $admin) {
            return null;
        }

        $target = Auth::user();

        if ($target instanceof User && $target->isNot($admin)) {
            activity('gestavi')
                ->causedBy($admin)
                ->performedOn($target)
                ->event('impersonation_ended')
                ->log('Fin de la connexion en tant que '.$target->name);
        }

        Session::forget(self::SESSION_KEY);
        Auth::login($admin);
        Session::regenerate();

        return $target instanceof User ? url('/admin/farm-users/'.$target->getKey()) : url('/admin');
    }
}
