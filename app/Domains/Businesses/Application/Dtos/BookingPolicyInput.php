<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\IncompleteBookingPolicy;

final readonly class BookingPolicyInput
{
    private const REQUIRED_KEYS = [
        'lead_time_minutes',
        'booking_window_minutes',
        'slot_granularity_minutes',
        'cancellation_window_minutes',
        'policy_message',
        'display_on_booking_page',
    ];

    /**
     * @param  list<string>  $absentKeys
     */
    public function __construct(
        public int $leadTimeMinutes,
        public ?int $bookingWindowMinutes,
        public int $slotGranularityMinutes,
        public ?int $cancellationWindowMinutes,
        public ?string $policyMessage,
        public bool $displayOnBookingPage,
        private array $absentKeys = [],
    ) {}

    public static function fromPayload(mixed $payload): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        return new self(
            leadTimeMinutes: self::numberOrZero($payload['lead_time_minutes'] ?? null),
            bookingWindowMinutes: self::numberOrNull($payload['booking_window_minutes'] ?? null),
            slotGranularityMinutes: self::numberOrZero($payload['slot_granularity_minutes'] ?? null),
            cancellationWindowMinutes: self::numberOrNull($payload['cancellation_window_minutes'] ?? null),
            policyMessage: self::textOrNull($payload['policy_message'] ?? null),
            displayOnBookingPage: self::flagOrFalse($payload['display_on_booking_page'] ?? null),
            absentKeys: self::absentKeysIn($payload),
        );
    }

    /**
     * @throws IncompleteBookingPolicy
     */
    public function validate(): void
    {
        if ($this->absentKeys === []) {
            return;
        }

        throw IncompleteBookingPolicy::missing($this->absentKeys);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    private static function absentKeysIn(array $payload): array
    {
        return array_values(array_filter(
            self::REQUIRED_KEYS,
            static fn (string $key): bool => ! array_key_exists($key, $payload),
        ));
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function numberOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function numberOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function flagOrFalse(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
