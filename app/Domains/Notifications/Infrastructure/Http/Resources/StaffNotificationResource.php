<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Resources;

use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffNotificationData $resource
 */
final class StaffNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'read_at' => $this->resource->readAt?->format(DATE_ATOM),
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
            'can_mark_as_read' => $this->resource->canMarkAsRead,
            'recipient' => [
                'id' => $this->resource->recipient->id,
                'name' => $this->resource->recipient->name,
            ],
            'details' => $this->resource->details,
        ];
    }
}
