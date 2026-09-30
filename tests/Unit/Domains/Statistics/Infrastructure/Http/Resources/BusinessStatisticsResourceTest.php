<?php

declare(strict_types=1);

use App\Domains\Statistics\Application\Dtos\AppointmentSummaryData;
use App\Domains\Statistics\Application\Dtos\BusinessStatisticsData;
use App\Domains\Statistics\Application\Dtos\CustomerSummaryData;
use App\Domains\Statistics\Application\Dtos\DailyCollectionData;
use App\Domains\Statistics\Application\Dtos\PaymentMethodShareData;
use App\Domains\Statistics\Application\Dtos\PeriodCollectionData;
use App\Domains\Statistics\Application\Dtos\StaffShareData;
use App\Domains\Statistics\Infrastructure\Http\Resources\BusinessStatisticsResource;
use Tests\TestCase;

uses(TestCase::class);

function businessStatisticsData(?float $changePercent = -5.67): BusinessStatisticsData
{
    return new BusinessStatisticsData(
        currencyCode: 'MXN',
        timezone: 'America/Monterrey',
        period: new PeriodCollectionData('2026-09-01', '2026-09-29', 28300),
        previousPeriod: new PeriodCollectionData('2026-08-01', '2026-08-29', 30000),
        changePercent: $changePercent,
        today: new DailyCollectionData('2026-09-29', 4500),
        lastSevenDays: [
            new DailyCollectionData('2026-09-28', 0),
            new DailyCollectionData('2026-09-29', 4500),
        ],
        byPaymentMethod: [new PaymentMethodShareData('cash', 28300, 70.6)],
        byStaff: [new StaffShareData('01930000-0000-7000-8000-0000000000d1', 'José Pablo', 'jose@example.com', 28300, 70.6, 7)],
        appointments: new AppointmentSummaryData(10, 7, 1, 2, ['admin' => 8, 'public' => 2]),
        customers: new CustomerSummaryData(6, 2, 4),
    );
}

/**
 * @return array<string, mixed>
 */
function businessStatisticsEnvelope(?BusinessStatisticsData $statistics = null): array
{
    return (array) BusinessStatisticsResource::make($statistics ?? businessStatisticsData())
        ->response()
        ->getData(true);
}

it('wraps the payload in the data envelope and nothing else', function () {
    expect(array_keys(businessStatisticsEnvelope()))->toBe(['data']);
});

it('serializes the whole contract the dashboard reads', function () {
    expect(businessStatisticsEnvelope()['data'])->toBe([
        'currency_code' => 'MXN',
        'timezone' => 'America/Monterrey',
        'period' => ['from' => '2026-09-01', 'to' => '2026-09-29', 'collected_cents' => 28300],
        'previous_period' => ['from' => '2026-08-01', 'to' => '2026-08-29', 'collected_cents' => 30000],
        'change_percent' => -5.67,
        'today' => ['date' => '2026-09-29', 'collected_cents' => 4500],
        'last_7_days' => [
            ['date' => '2026-09-28', 'collected_cents' => 0],
            ['date' => '2026-09-29', 'collected_cents' => 4500],
        ],
        'by_payment_method' => [
            ['code' => 'cash', 'collected_cents' => 28300, 'share_percent' => 70.6],
        ],
        'by_staff' => [[
            'id' => '01930000-0000-7000-8000-0000000000d1',
            'name' => 'José Pablo',
            'email' => 'jose@example.com',
            'collected_cents' => 28300,
            'share_percent' => 70.6,
            'attended_appointments' => 7,
        ]],
        'appointments' => [
            'total' => 10,
            'attended' => 7,
            'cancelled' => 1,
            'upcoming' => 2,
            'by_source' => ['admin' => 8, 'public' => 2],
        ],
        'customers' => ['attended' => 6, 'new' => 2, 'returning' => 4],
    ]);
});

it('keeps change_percent on the wire as null when there is nothing to compare against', function () {
    $serialized = businessStatisticsEnvelope(businessStatisticsData(changePercent: null))['data'];

    expect($serialized)->toHaveKey('change_percent')
        ->and($serialized['change_percent'])->toBeNull();
});

it('carries no customer data and no row number', function () {
    $json = json_encode(businessStatisticsEnvelope()['data'], JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('customer_id')
        ->not->toContain('business_id')
        ->not->toContain('"id":1')
        ->not->toContain('account_id');
});
