@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $announcements = $tenant instanceof \App\Models\Organisation
        ? \App\Models\Announcement::query()->current()->forOrganisation($tenant)->orderByDesc('level')->orderByDesc('starts_at')->limit(3)->get()
        : collect();
@endphp

@foreach ($announcements as $announcement)
    @php
        $tone = match ($announcement->level) {
            \App\Enums\AnnouncementLevel::Danger => 'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-100',
            \App\Enums\AnnouncementLevel::Warning => 'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100',
            default => 'border-info-300 bg-info-50 text-info-900 dark:border-info-700 dark:bg-info-950 dark:text-info-100',
        };
    @endphp
    <div
        x-data="{ hidden: (() => { try { return localStorage.getItem('gestavi-announcement-{{ $announcement->id }}') === '1'; } catch (e) { return false; } })() }"
        x-show="!hidden"
        x-cloak
        class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $tone }}"
    >
        <div>
            <span class="font-semibold">{{ $announcement->title }}</span>
            — {!! nl2br(e($announcement->body)) !!}
        </div>
        <button
            type="button"
            class="flex-shrink-0 rounded px-2 text-xs font-medium underline opacity-80 hover:opacity-100"
            x-on:click="hidden = true; try { localStorage.setItem('gestavi-announcement-{{ $announcement->id }}', '1'); } catch (e) {}"
        >Masquer</button>
    </div>
@endforeach
