<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Resources;

use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PublicGuestBookingConfirmation $resource
 */
final class PublicBookingConfirmationResource extends JsonResource
{
    /**
     * @return array{booking: array<string, mixed>, manage_token: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'booking' => PublicBookingResource::make($this->resource->booking)->toArray($request),
            'manage_token' => $this->resource->manageToken,
        ];
    }
}
