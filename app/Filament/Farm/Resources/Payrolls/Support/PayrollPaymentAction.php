<?php

namespace App\Filament\Farm\Resources\Payrolls\Support;

use App\Exceptions\AccountException;
use App\Exceptions\ExpenseException;
use App\Models\Account;
use App\Models\Payroll;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logique partagée entre la table et la page de vue des bulletins :
 * formulaire et traitement du paiement, génération du bulletin PDF.
 */
class PayrollPaymentAction
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Select::make('account_id')
                ->label('Compte à débiter')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Account::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Account $account): array => [
                            $account->id => $account->name.' — '.number_format((float) $account->current_balance, 0, ',', ' ').' XOF',
                        ])
                        ->toArray();
                })
                ->required()
                ->searchable()
                ->native(false),

            DatePicker::make('paid_at')
                ->label('Date de paiement')
                ->required()
                ->default(now())
                ->maxDate(now())
                ->native(false)
                ->displayFormat('d/m/Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(Payroll $payroll, array $data): bool
    {
        try {
            app(PayrollService::class)->markAsPaid(
                $payroll,
                auth()->id(),
                (int) $data['account_id'],
                Carbon::parse($data['paid_at']),
            );
        } catch (AccountException|ExpenseException|\LogicException $e) {
            Notification::make()
                ->title('Paiement impossible')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        Notification::make()
            ->title('Bulletin payé')
            ->body("Le bulletin de {$payroll->employee?->full_name} pour {$payroll->getPeriodLabel()} a été payé et comptabilisé.")
            ->success()
            ->send();

        return true;
    }

    public static function downloadPayslip(Payroll $payroll): StreamedResponse
    {
        $payroll->load(['employee', 'contract', 'items', 'parameter', 'expense']);

        $organisation = Filament::getTenant() ?? $payroll->employee?->organisation;
        $service = app(PayrollService::class);
        $params = $payroll->parameter ?? $service->parametersFor(
            $payroll->employee?->organisation_id,
            Carbon::create($payroll->period_year, $payroll->period_month, 1),
        );

        $pdf = Pdf::loadView('pdf.payslip', [
            'payroll' => $payroll,
            'employee' => $payroll->employee,
            'contract' => $payroll->contract,
            'organisation' => $organisation,
            'parameter' => $params,
            'employerBreakdown' => $service->employerContributionsBreakdown((float) $payroll->gross_salary, $params),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'bulletin-%d-%02d-%s.pdf',
            $payroll->period_year,
            $payroll->period_month,
            $payroll->employee?->employee_number ?? $payroll->id,
        );

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
