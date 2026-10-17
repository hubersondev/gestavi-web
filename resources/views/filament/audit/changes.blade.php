@php
    /** @var \App\Models\AuditLog $entry */
    $rows = $entry->diff();
    $isUpdate = $entry->event === 'updated';
    $isDelete = $entry->event === 'deleted';
@endphp

@if ($rows === [])
    <p class="text-sm text-gray-500">Aucun champ à afficher pour cette entrée.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2">Champ</th>
                    @if ($isUpdate || $isDelete)
                        <th class="px-3 py-2">{{ $isDelete ? 'Valeur supprimée' : 'Avant' }}</th>
                    @endif
                    @if (! $isDelete)
                        <th class="px-3 py-2">{{ $isUpdate ? 'Après' : 'Valeur' }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b dark:border-gray-700 align-top">
                        <td class="px-3 py-2 font-medium whitespace-nowrap">{{ str_replace('_', ' ', $row['field']) }}</td>
                        @if ($isUpdate || $isDelete)
                            <td class="px-3 py-2 text-gray-500 break-all">{{ \App\Models\AuditLog::formatValue($row['old']) }}</td>
                        @endif
                        @if (! $isDelete)
                            <td class="px-3 py-2 break-all {{ $isUpdate ? 'font-semibold text-warning-700 dark:text-warning-300' : '' }}">{{ \App\Models\AuditLog::formatValue($row['new']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-gray-500">
        {{ $entry->subjectTypeLabel() }} n° {{ $entry->subject_id }}
        @if ($entry->batch_uuid) · lot {{ \Illuminate\Support\Str::limit($entry->batch_uuid, 8, '') }} @endif
    </p>
@endif
