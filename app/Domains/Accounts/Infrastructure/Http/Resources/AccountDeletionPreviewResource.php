<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Resources;

use App\Domains\Accounts\Application\Dtos\AccountDeletionPreviewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccountDeletionPreviewData $resource
 */
final class AccountDeletionPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ownedBusiness = $this->resource->ownedBusiness;

        return [
            'email' => $this->resource->email,
            'has_password' => $this->resource->hasPassword,
            'owned_business' => $ownedBusiness === null ? null : [
                'id' => $ownedBusiness->id,
                'name' => $ownedBusiness->name,
            ],
            'upcoming_appointments_count' => $this->resource->upcomingAppointmentsCount,
            'blocked_by' => $this->resource->blockedBy?->value,
            'grace_period_ends_at' => $this->resource->gracePeriodEndsAt->format(DATE_ATOM),
        ];
    }
}
