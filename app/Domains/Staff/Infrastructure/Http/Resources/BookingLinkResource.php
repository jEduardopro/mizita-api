<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Resources;

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BookingLinkData $resource
 */
final class BookingLinkResource extends JsonResource
{
    /**
     * @return array{booking_slug: string, booking_url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'booking_slug' => $this->resource->slug,
            'booking_url' => $this->resource->url,
        ];
    }
}
