<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Http\Resources;

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MyScheduleData $resource
 */
final class MyScheduleResource extends JsonResource
{
    /**
     * @return array{inherited: bool, schedule: list<array{weekday: int, starts_at: string, ends_at: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'inherited' => $this->resource->inherited,
            'schedule' => array_map(
                static fn (ScheduleRuleData $rule): array => [
                    'weekday' => $rule->weekday,
                    'starts_at' => $rule->startsAt,
                    'ends_at' => $rule->endsAt,
                ],
                $this->resource->schedule,
            ),
        ];
    }
}
