<?php

namespace App\Filament\Farm\Pages;

use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Services\Imports\ExcelImportService;
use App\Services\Imports\ImportDefinition;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Import Excel des référentiels et des pièces : modèle par type, contrôle complet du fichier,
 * import en une transaction. Réservé aux membres qui administrent les paramètres.
 */
class DataImport extends Page
{
    use RequiresOrganisationPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Import de données';

    protected static ?string $title = 'Import de données';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'import';

    protected string $view = 'filament.farm.pages.data-import';

    protected static string $requiredPermission = 'settings.update';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array{imported: int, rows: int, errors: list<string>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill(['type' => 'customers']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type de données')
                    ->options(collect($this->importService()->definitions())->map(fn (ImportDefinition $definition): string => $definition->label())->all())
                    ->required()
                    ->live()
                    ->native(false)
                    ->helperText(fn (Get $get): string => $get('type') ? $this->importService()->definition($get('type'))->description() : ''),
                FileUpload::make('file')
                    ->label('Fichier Excel (.xlsx) ou CSV')
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                    ])
                    ->maxSize(10240)
                    ->required()
                    ->helperText('Partez du modèle téléchargeable : même en-tête, une ligne par enregistrement, dates JJ/MM/AAAA.'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Télécharger le modèle')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): BinaryFileResponse => $this->downloadTemplate()),
        ];
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $key = $this->data['type'] ?? 'customers';
        $definition = $this->importService()->definition($key);
        $path = $this->importService()->writeTemplate($key);

        return response()->download($path, 'modele-'.$definition->key().'.xlsx')->deleteFileAfterSend();
    }

    public function import(): void
    {
        $state = $this->form->getState();
        $file = is_array($state['file']) ? reset($state['file']) : $state['file'];
        $path = Storage::disk('local')->path($file);

        try {
            $this->result = $this->importService()->import(
                $state['type'],
                $path,
                Filament::getTenant(),
                Filament::auth()->id(),
            );
        } finally {
            Storage::disk('local')->delete($file);
        }

        if ($this->result['errors'] === []) {
            Notification::make()
                ->title('Import terminé')
                ->body(sprintf('%d enregistrement(s) créé(s) à partir de %d ligne(s).', $this->result['imported'], $this->result['rows']))
                ->success()
                ->send();

            $this->form->fill(['type' => $state['type']]);

            return;
        }

        Notification::make()
            ->title('Fichier refusé, rien n\'a été importé')
            ->body(sprintf('%d erreur(s) : corrigez le fichier puis recommencez.', count($this->result['errors'])))
            ->danger()
            ->send();
    }

    private function importService(): ExcelImportService
    {
        return app(ExcelImportService::class);
    }
}
