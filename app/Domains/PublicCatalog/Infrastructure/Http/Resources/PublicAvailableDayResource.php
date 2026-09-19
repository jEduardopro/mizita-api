<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Resources;

use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PublicAvailableDay $resource
 */
final class PublicAvailableDayResource extends JsonResource
{
    /**
     * @return array{date: string, starts: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            'starts' => array_map(
                static fn (DateTimeImmutable $start): string => $start->format(DATE_ATOM),
                $this->resource->starts,
            ),
        ];
    }
}
