<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\Exceptions\MissingGuestContactField;

final readonly class PublicBookingRequest
{
    public const MAXIMUM_NOTES_LENGTH = 2000;

    public function __construct(
        public string $serviceId,
        public string $staffMemberId,
        public string $startsAt,
        public PublicGuestDetails $guest,
        public ?string $notes,
    ) {}

    /**
     * @throws InvalidPublicGuestAddress
     */
    public function validate(): void
    {
        $this->guest->validate();
    }

    /**
     * @throws MissingGuestContactField
     */
    public function collectingOnly(GuestFormFields $fields): self
    {
        return new self(
            serviceId: $this->serviceId,
            staffMemberId: $this->staffMemberId,
            startsAt: $this->startsAt,
            guest: $fields->applyTo($this->guest),
            notes: $this->notes,
        );
    }
}
