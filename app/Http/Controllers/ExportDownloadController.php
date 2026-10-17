<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement d'un export de rapport par lien signé (route `exports.download`), utilisé quand
 * le disque des exports ne sait pas produire d'URL temporaire (disque local). Le lien est
 * personnel : il porte l'identifiant de l'utilisateur destinataire et expire avec sa signature.
 */
class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        abort_unless((int) $request->query('user') === (int) $request->user()?->getKey(), 403, 'Ce lien de téléchargement ne vous est pas destiné.');
        abort_unless(str_starts_with($path, 'exports/') && ! str_contains($path, '..'), 404);

        $disk = Storage::disk(GenerateReportExport::disk());

        abort_unless($disk->exists($path), 404, 'Cet export n\'existe plus : relancez-le depuis le rapport.');

        return $disk->download($path, basename($path), ['Content-Type' => GenerateReportExport::contentType(pathinfo($path, PATHINFO_EXTENSION))]);
    }
}
