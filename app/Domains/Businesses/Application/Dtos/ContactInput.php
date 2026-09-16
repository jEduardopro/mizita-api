<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\ValueObjects\ContactEmail;

final readonly class ContactInput
{
    public function __construct(
        public ?string $contactEmail,
        public ?PhoneNumberInput $phone,
    ) {}

    /**
     * @throws InvalidBusinessContactEmail
     * @throws UnsupportedPhoneNumber
     */
    public function validate(): void
    {
        $this->validateContactEmail();
        $this->phone?->validate();
    }

    private function validateContactEmail(): void
    {
        if ($this->contactEmail === null) {
            return;
        }

        ContactEmail::fromString($this->contactEmail);
    }
}
