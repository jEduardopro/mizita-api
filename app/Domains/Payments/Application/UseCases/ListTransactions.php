<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\ListTransactionsInput;
use App\Domains\Payments\Application\Dtos\PaymentTransactionReportData;
use App\Domains\Payments\Contracts\BusinessTimezone;
use App\Domains\Payments\Contracts\PaymentReports;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListTransactions
{
    public function __construct(
        private readonly PaymentReports $reports,
        private readonly BusinessContext $business,
        private readonly BusinessTimezone $timezones,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<PaymentTransactionReportData>>
     */
    public function handle(ListTransactionsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $page = $this->reports->transactionsPage(
                $businessId,
                $input->toQuery($this->windowOf($businessId, $input->period())),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($page->map(PaymentTransactionReportData::fromRecord(...)));
    }

    private function windowOf(string $businessId, ?ReportPeriod $period): ?ReportWindow
    {
        if ($period === null) {
            return null;
        }

        return $period->windowIn($this->timezones->timezoneOf($businessId));
    }
}
