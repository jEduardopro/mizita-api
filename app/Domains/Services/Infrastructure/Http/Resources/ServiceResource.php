<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Resources;

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ServiceData $resource
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'duration_minutes' => $this->resource->durationMinutes,
            'buffer_minutes' => $this->resource->bufferMinutes,
            'price' => $this->resource->price,
            'color' => $this->resource->color->value,
            'active' => $this->resource->active,
            'image_url' => $this->resource->imageUrl,
            'booking_url' => $this->resource->bookingUrl,
            'staff' => array_map(self::describeStaffMember(...), $this->resource->staff),
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    private static function describeStaffMember(StaffMemberSnapshot $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
        ];
    }
}
