<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Infrastructure\Eloquent\Factories;

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Models\BookingPolicyModel;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPolicyModel>
 */
final class BookingPolicyModelFactory extends Factory
{
    protected $model = BookingPolicyModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'lead_time_minutes' => BookingPolicy::DEFAULT_LEAD_TIME_MINUTES,
            'booking_window_minutes' => BookingPolicy::DEFAULT_BOOKING_WINDOW_MINUTES,
            'slot_granularity_minutes' => BookingPolicy::DEFAULT_SLOT_GRANULARITY_MINUTES,
            'cancellation_window_minutes' => BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES,
            'policy_message' => null,
            'display_on_booking_page' => BookingPolicy::DEFAULT_DISPLAY_ON_BOOKING_PAGE,
            'phone_field' => ContactFields::DEFAULT_PHONE,
            'email_field' => ContactFields::DEFAULT_EMAIL,
            'address_field' => ContactFields::DEFAULT_ADDRESS,
        ];
    }
}
