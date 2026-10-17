<?php

use App\Enums\PlanFeature;
use App\Enums\TransactionCategory;
use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Jobs\GenerateReportExport;
use App\Models\Account;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\actingAs;

/**
 * Exports de rapports : job en file d'attente, fichier stocké sur le disque privé,
 * notification avec lien de téléchargement ; repli synchrone quand la file est `sync`.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $account = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0]);
    $account->credit(1500, 'Vente œufs', TransactionCategory::Payment);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $this->filters = ['date_from' => now()->subDays(5)->toDateString(), 'date_to' => now()->toDateString(), 'farm_id' => null, 'account_id' => null];
});

it('dispatch le job quand une file d\'attente est configurée et notifie la mise en file', function () {
    Queue::fake();
    config(['queue.default' => 'database']);

    $page = app(FinancialReportPage::class);
    $page->filters = $this->filters;

    expect($page->exportExcel())->toBeNull();

    Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job): bool => $job->report === 'financier'
        && $job->format === 'xlsx'
        && $job->organisationId === $this->organisation->id
        && $job->userId === $this->user->id);
});

it('retourne directement le fichier quand la file est synchrone', function () {
    config(['queue.default' => 'sync']);

    $page = app(FinancialReportPage::class);
    $page->filters = $this->filters;

    $response = $page->exportPdf();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toBe('application/pdf');
});

it('le job génère le fichier sur le disque local et notifie avec un lien signé, personnel et téléchargeable', function () {
    Storage::fake('local');
    Notification::fake();

    $job = new GenerateReportExport('financier', 'xlsx', $this->filters, $this->organisation->id, $this->user->id);
    $path = $job->handle();

    Storage::disk('local')->assertExists($path);

    expect($path)->toStartWith("exports/{$this->organisation->id}/")
        ->and($path)->toEndWith('.xlsx')
        ->and(Storage::disk('local')->size($path))->toBeGreaterThan(1000);

    $url = null;
    Notification::assertSentTo($this->user, OrganisationAlertNotification::class, function (OrganisationAlertNotification $n) use (&$url): bool {
        $url = $n->actionUrl;

        return $n->alertKey === 'report_export' && str_contains($n->title, 'financier') && $n->actionLabel === 'Télécharger';
    });

    expect($url)->toContain('/exports/'.$path, 'signature=');

    // Le destinataire télécharge ; un autre utilisateur est refusé ; sans signature, refusé
    $this->get($url)->assertOk()->assertHeader('content-type', GenerateReportExport::contentType('xlsx'));
    actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
    actingAs($this->user);
    $this->get(route('exports.download', ['path' => $path, 'user' => $this->user->id]))->assertForbidden();
});

it('avec un disque S3 (MinIO), le lien est l\'URL temporaire du serveur d\'objets', function () {
    config(['filesystems.exports_disk' => 's3']);
    Storage::fake('s3')->buildTemporaryUrlsUsing(fn (string $path, $expiration): string => 'https://minio.example/gestavi/'.$path.'?X-Amz-Expires=86400');
    Notification::fake();

    $path = (new GenerateReportExport('financier', 'xlsx', $this->filters, $this->organisation->id, $this->user->id))->handle();

    Storage::disk('s3')->assertExists($path);
    Notification::assertSentTo($this->user, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->actionUrl === 'https://minio.example/gestavi/'.$path.'?X-Amz-Expires=86400');
});

it('génère aussi les exports production et stocks en PDF ou XLSX', function () {
    Storage::fake('local');
    Notification::fake();

    $pdf = (new GenerateReportExport('production', 'pdf', ['date_from' => now()->subDays(5)->toDateString(), 'date_to' => now()->toDateString()], $this->organisation->id, $this->user->id))->handle();
    $xlsx = (new GenerateReportExport('stocks', 'xlsx', ['date_from' => now()->subDays(5)->toDateString(), 'date_to' => now()->toDateString()], $this->organisation->id, $this->user->id))->handle();

    expect(Storage::disk('local')->get($pdf))->toStartWith('%PDF')
        ->and($xlsx)->toEndWith('.xlsx');
});
