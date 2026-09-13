<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Resources;

use App\Domains\Businesses\Application\Dtos\BusinessData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BusinessData $resource
 */
final class BusinessResource extends JsonResource
{
    /**
     * The business a caller owns, as the dashboard needs it.
     *
     * id is the uuid, never the internal key, and industry_id is the
     * industry's uuid for the same reason. Timestamps go out as DATE_ATOM in
     * UTC, so the client converts once, from an instant, into whatever local
     * time it is showing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'timezone' => $this->resource->timezone,
            'industry_id' => $this->resource->industryId,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }
}
