<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestDetails;

final readonly class BookPublicAppointmentInput
{
    public function __construct(
        public string $slug,
        public PublicBookingRequest $booking,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $slug): self
    {
        return new self(
            slug: $slug,
            booking: new PublicBookingRequest(
                serviceId: self::textOrEmpty($payload['service_id'] ?? null),
                staffMemberId: self::textOrEmpty($payload['staff_member_id'] ?? null),
                startsAt: self::textOrEmpty($payload['starts_at'] ?? null),
                guest: self::guestDetailsFrom($payload['guest'] ?? null),
                notes: self::textOrNull($payload['notes'] ?? null),
            ),
        );
    }

    /**
     * @throws BusinessPageNotFound
     */
    public function validate(): void
    {
        $this->validateSlug();
    }

    private static function guestDetailsFrom(mixed $payload): PublicGuestDetails
    {
        $guest = is_array($payload) ? $payload : [];
        $phone = is_array($guest['phone'] ?? null) ? $guest['phone'] : [];

        return new PublicGuestDetails(
            name: self::textOrEmpty($guest['name'] ?? null),
            email: self::textOrNull($guest['email'] ?? null),
            phoneCountryCode: self::textOrNull($phone['country_code'] ?? null),
            phoneNationalNumber: self::textOrNull($phone['national_number'] ?? null),
        );
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function validateSlug(): void
    {
        BusinessPageSlug::fromString($this->slug);
    }
}
