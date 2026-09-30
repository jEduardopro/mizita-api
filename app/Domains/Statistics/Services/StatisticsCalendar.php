<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Services;

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\StatisticsPeriod;
use App\Domains\Statistics\ValueObjects\StatisticsWindows;
use DateTimeImmutable;
use DateTimeZone;

final class StatisticsCalendar
{
    public const TRAILING_DAYS = 7;

    /**
     * @throws InvalidStatisticsPeriod
     */
    public function windowsFor(DateTimeImmutable $now, DateTimeZone $zone, ?StatisticsPeriod $requested): StatisticsWindows
    {
        $today = LocalDate::at($now, $zone);
        $current = $requested ?? StatisticsPeriod::monthToDate($today);

        $current->assertEndsNoLaterThan($today);

        return new StatisticsWindows(
            current: $current->windowIn($zone),
            previous: $current->previousMonth()->windowIn($zone),
            today: StatisticsPeriod::singleDay($today)->windowIn($zone),
            lastSevenDays: StatisticsPeriod::trailingDaysEndingOn($today, self::TRAILING_DAYS)->windowIn($zone),
        );
    }
}
