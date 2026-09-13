<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Resources;

use App\Domains\Businesses\Application\Dtos\NameAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NameAvailability $resource
 */
final class BusinessNameAvailabilityResource extends JsonResource
{
    /**
     * Both keys are nullable and mutually exclusive: a free name carries the
     * address it would get, a rejected one carries why. The reason is a machine
     * code the client turns into its own wording, not a sentence.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available' => $this->resource->available,
            'slug' => $this->resource->slug,
            'reason' => $this->resource->reason?->value,
        ];
    }
}
