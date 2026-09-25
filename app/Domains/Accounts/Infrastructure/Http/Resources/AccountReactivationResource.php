<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\AccountReactivationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccountReactivationData $resource
 */
final class AccountReactivationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $business = $this->resource->business;

        return [
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'deletion_requested_at' => $this->resource->deletionRequestedAt->format(DATE_ATOM),
            'grace_period_ends_at' => $this->resource->gracePeriodEndsAt->format(DATE_ATOM),
            'business' => $business === null ? null : [
                'name' => $business->name,
                'closed_at' => $business->closedAt->format(DATE_ATOM),
                'purge_scheduled_at' => $business->purgeScheduledAt->format(DATE_ATOM),
                'purged' => $business->purged,
            ],
        ];
    }
}
