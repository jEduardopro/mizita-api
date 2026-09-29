<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Resources;

use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SubscriptionData $resource
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array{
     *     id: ?string,
     *     plan: string,
     *     status: ?string,
     *     started_at: ?string,
     *     current_period_ends_at: ?string,
     *     canceled_at: ?string,
     *     payment_grace_ends_at: ?string,
     *     can_checkout: bool,
     *     can_switch_to_free: bool,
     *     can_resume: bool,
     *     can_manage_billing: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'plan' => $this->resource->plan->value,
            'status' => $this->resource->status?->value,
            'started_at' => $this->resource->startedAt?->format(DATE_ATOM),
            'current_period_ends_at' => $this->resource->currentPeriodEndsAt?->format(DATE_ATOM),
            'canceled_at' => $this->resource->canceledAt?->format(DATE_ATOM),
            'payment_grace_ends_at' => $this->resource->paymentGraceEndsAt?->format(DATE_ATOM),
            'can_checkout' => $this->resource->canCheckout,
            'can_switch_to_free' => $this->resource->canSwitchToFree,
            'can_resume' => $this->resource->canResume,
            'can_manage_billing' => $this->resource->canManageBilling,
        ];
    }
}
