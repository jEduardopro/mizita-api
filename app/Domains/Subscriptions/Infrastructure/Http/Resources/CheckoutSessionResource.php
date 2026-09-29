<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Resources;

use App\Domains\Subscriptions\Application\Dtos\CheckoutSessionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CheckoutSessionData $resource
 */
final class CheckoutSessionResource extends JsonResource
{
    /**
     * @return array{session_id: string, client_secret: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->resource->sessionId,
            'client_secret' => $this->resource->clientSecret,
        ];
    }
}
