<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Resources;

use App\Domains\Businesses\Application\Dtos\CalendarSettingsData;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CalendarSettingsData $resource
 */
final class CalendarSettingsResource extends JsonResource
{
    /**
     * @return array{timezone: string, currency_code: string, schedule: list<array{weekday: int, starts_at: string, ends_at: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'timezone' => $this->resource->timezone,
            'currency_code' => $this->resource->currencyCode,
            'schedule' => array_map(self::describeScheduleEntry(...), $this->resource->schedule),
        ];
    }

    /**
     * @return array{weekday: int, starts_at: string, ends_at: string}
     */
    private static function describeScheduleEntry(BusinessScheduleEntry $entry): array
    {
        return [
            'weekday' => $entry->weekday,
            'starts_at' => $entry->startsAt,
            'ends_at' => $entry->endsAt,
        ];
    }
}
