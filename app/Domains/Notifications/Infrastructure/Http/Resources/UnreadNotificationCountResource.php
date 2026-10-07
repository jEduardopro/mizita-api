<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Resources;

use App\Domains\Notifications\Application\Dtos\UnreadNotificationCountData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read UnreadNotificationCountData $resource
 */
final class UnreadNotificationCountResource extends JsonResource
{
    /**
     * @return array{count: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'count' => $this->resource->count,
        ];
    }
}
