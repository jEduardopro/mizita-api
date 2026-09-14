<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

/**
 * Every field is what the caller submitted, untouched: nothing here has been
 * judged acceptable yet, because that is the use case's job and a DTO carrying
 * a parsed value would mean the decision was already taken at the edge.
 *
 * There is no slug field. The address is derived from the name, so accepting
 * one here would be accepting a second, contradictory source for it.
 */
final readonly class OnboardBusinessInput
{
    public function __construct(
        public string $ownerAccountId,
        public string $name,
        public string $timezone,
        public string $industryId,
        public ?PhoneNumberInput $phone = null,
    ) {}

    /**
     * The owner arrives as an argument rather than a payload key: a client that
     * could name the owner could hand a business to a stranger.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $ownerAccountId): self
    {
        return new self(
            ownerAccountId: $ownerAccountId,
            name: (string) $payload['name'],
            timezone: (string) $payload['timezone'],
            industryId: (string) $payload['industry_id'],
            phone: self::submittedPhone($payload),
        );
    }

    /**
     * Null means the owner skipped the number, and only that. An unreachable
     * number is the use case's verdict, thrown as UnsupportedPhoneNumber.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function submittedPhone(array $payload): ?PhoneNumberInput
    {
        $phone = (array) ($payload['phone'] ?? []);

        if ($phone === []) {
            return null;
        }

        return new PhoneNumberInput(
            countryCode: (string) $phone['country_code'],
            nationalNumber: (string) $phone['national_number'],
        );
    }
}
