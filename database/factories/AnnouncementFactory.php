<?php

namespace Database\Factories;

use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Maintenance programmée',
            'body' => 'La plateforme sera indisponible dimanche de 2 h à 4 h pour une mise à jour.',
            'level' => AnnouncementLevel::Info,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ];
    }
}
