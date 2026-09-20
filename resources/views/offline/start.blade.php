<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GESTAVI Terrain</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
</head>
<body style="font-family: system-ui, sans-serif; text-align: center; padding: 3rem 1rem; background: #f9fafb; color: #111827;">
    <h1 style="color:#15803d">GESTAVI Terrain</h1>
    <p>Ouverture de votre espace de saisie…</p>
    <p><a id="fallback" href="{{ url('/farm') }}">Aller au panel</a></p>
    <script>
        (function () {
            try {
                var last = localStorage.getItem('gestavi.last_terrain_url');
                if (last) {
                    window.location.replace(last);
                    return;
                }
            } catch (e) {}
            document.getElementById('fallback').textContent = 'Connectez-vous puis ouvrez « Saisie terrain » depuis le menu.';
        })();
    </script>
</body>
</html>
