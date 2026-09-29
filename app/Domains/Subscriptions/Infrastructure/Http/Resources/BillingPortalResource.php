<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Resources;

use App\Domains\Subscriptions\Application\Dtos\BillingPortalData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read BillingPortalData $resource
 */
final class BillingPortalResource extends JsonResource
{
    /**
     * @return array{url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource->url,
        ];
    }
}
