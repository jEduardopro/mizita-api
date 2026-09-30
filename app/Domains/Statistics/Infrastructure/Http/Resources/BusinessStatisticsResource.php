<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Http\Resources;

use App\Domains\Statistics\Application\Dtos\BusinessStatisticsData;
use App\Domains\Statistics\Application\Dtos\DailyCollectionData;
use App\Domains\Statistics\Application\Dtos\PaymentMethodShareData;
use App\Domains\Statistics\Application\Dtos\PeriodCollectionData;
use App\Domains\Statistics\Application\Dtos\StaffShareData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessStatisticsData $resource
 */
final class BusinessStatisticsResource extends JsonResource
{
    /**
     * @return array{
     *     currency_code: string,
     *     timezone: string,
     *     period: array{from: string, to: string, collected_cents: int},
     *     previous_period: array{from: string, to: string, collected_cents: int},
     *     change_percent: ?float,
     *     today: array{date: string, collected_cents: int},
     *     last_7_days: list<array{date: string, collected_cents: int}>,
     *     by_payment_method: list<array{code: string, collected_cents: int, share_percent: float}>,
     *     by_staff: list<array{id: string, name: string, email: string, collected_cents: int, share_percent: float, attended_appointments: int}>,
     *     appointments: array{total: int, attended: int, cancelled: int, upcoming: int, by_source: array<string, int>},
     *     customers: array{attended: int, new: int, returning: int},
     * }
     */
    public function toArray(Request $request): array
    {
        $statistics = $this->resource;

        return [
            'currency_code' => $statistics->currencyCode,
            'timezone' => $statistics->timezone,
            'period' => self::period($statistics->period),
            'previous_period' => self::period($statistics->previousPeriod),
            'change_percent' => $statistics->changePercent,
            'today' => self::day($statistics->today),
            'last_7_days' => array_map(self::day(...), $statistics->lastSevenDays),
            'by_payment_method' => array_map(self::paymentMethod(...), $statistics->byPaymentMethod),
            'by_staff' => array_map(self::staffMember(...), $statistics->byStaff),
            'appointments' => [
                'total' => $statistics->appointments->total,
                'attended' => $statistics->appointments->attended,
                'cancelled' => $statistics->appointments->cancelled,
                'upcoming' => $statistics->appointments->upcoming,
                'by_source' => $statistics->appointments->bySource,
            ],
            'customers' => [
                'attended' => $statistics->customers->attended,
                'new' => $statistics->customers->new,
                'returning' => $statistics->customers->returning,
            ],
        ];
    }

    /**
     * @return array{from: string, to: string, collected_cents: int}
     */
    private static function period(PeriodCollectionData $period): array
    {
        return [
            'from' => $period->from,
            'to' => $period->to,
            'collected_cents' => $period->collectedCents,
        ];
    }

    /**
     * @return array{date: string, collected_cents: int}
     */
    private static function day(DailyCollectionData $day): array
    {
        return [
            'date' => $day->date,
            'collected_cents' => $day->collectedCents,
        ];
    }

    /**
     * @return array{code: string, collected_cents: int, share_percent: float}
     */
    private static function paymentMethod(PaymentMethodShareData $method): array
    {
        return [
            'code' => $method->code,
            'collected_cents' => $method->collectedCents,
            'share_percent' => $method->sharePercent,
        ];
    }

    /**
     * @return array{id: string, name: string, email: string, collected_cents: int, share_percent: float, attended_appointments: int}
     */
    private static function staffMember(StaffShareData $staffMember): array
    {
        return [
            'id' => $staffMember->id,
            'name' => $staffMember->name,
            'email' => $staffMember->email,
            'collected_cents' => $staffMember->collectedCents,
            'share_percent' => $staffMember->sharePercent,
            'attended_appointments' => $staffMember->attendedAppointments,
        ];
    }
}
