<?php

namespace App\Http\Controllers;

use App\Services\Impersonation;
use Illuminate\Http\RedirectResponse;

/**
 * Fin d'une session « se connecter en tant que » : retour au compte administrateur.
 */
class ImpersonationController extends Controller
{
    public function leave(): RedirectResponse
    {
        $url = Impersonation::leave();

        return redirect()->to($url ?? url('/admin'));
    }
}
