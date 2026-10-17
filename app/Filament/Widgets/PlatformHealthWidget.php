<?php

namespace App\Filament\Widgets;

use App\Services\PlatformHealth;
use Filament\Widgets\Widget;

/**
 * État d'exploitation sur le tableau de bord Admin : les contrôles peu coûteux du diagnostic.
 */
class PlatformHealthWidget extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.platform-health';

    /**
     * @return array{checks: list<array{check: string, status: string, detail: string}>, summary: array{ok: int, warn: int, fail: int}}
     */
    public function getHealth(): array
    {
        $health = app(PlatformHealth::class);

        return ['checks' => $health->checks(), 'summary' => $health->summary()];
    }
}
