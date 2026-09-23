<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
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
     * @throws InvalidPublicGuestAddress
     */
    public function validate(): void
    {
        $this->validateSlug();
        $this->validateBooking();
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
            address: self::guestAddressFrom($guest['address'] ?? null),
        );
    }

    private static function guestAddressFrom(mixed $payload): ?PublicGuestAddress
    {
        if (! is_array($payload)) {
            return null;
        }

        return new PublicGuestAddress(
            street: self::textOrNull($payload['street'] ?? null),
            city: self::textOrNull($payload['city'] ?? null),
            state: self::textOrNull($payload['state'] ?? null),
            postalCode: self::textOrNull($payload['postal_code'] ?? null),
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

    private function validateBooking(): void
    {
        $this->booking->validate();
    }
}
