<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Infrastructure\Eloquent\Mappers;

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Models\BookingPolicyModel;
use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use DateTimeImmutable;

final class BookingPolicyMapper
{
    public function toEntity(BookingPolicyModel $model, string $businessId): BookingPolicy
    {
        return BookingPolicy::restore(
            id: $model->uuid,
            businessId: $businessId,
            leadTime: LeadTime::restore($model->lead_time_minutes),
            bookingWindow: BookingWindow::restore($model->booking_window_minutes),
            slotGranularity: SlotGranularity::restore($model->slot_granularity_minutes),
            cancellationWindow: CancellationWindow::restore($model->cancellation_window_minutes),
            policyMessage: PolicyMessage::restore($model->policy_message),
            displayedOnBookingPage: $model->display_on_booking_page,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(BookingPolicy $policy, int $businessKey): array
    {
        return [
            'uuid' => $policy->id,
            'business_id' => $businessKey,
            'lead_time_minutes' => $policy->leadTime()->minutes,
            'booking_window_minutes' => $policy->bookingWindow()->minutes(),
            'slot_granularity_minutes' => $policy->slotGranularity()->minutes,
            'cancellation_window_minutes' => $policy->cancellationWindow()->minutes,
            'policy_message' => $policy->policyMessage()->toString(),
            'display_on_booking_page' => $policy->isDisplayedOnBookingPage(),
        ];
    }
}
