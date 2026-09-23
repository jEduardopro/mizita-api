<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

final readonly class ContactFields
{
    public const DEFAULT_PHONE = ContactFieldRequirement::Required;

    public const DEFAULT_EMAIL = ContactFieldRequirement::Optional;

    public const DEFAULT_ADDRESS = ContactFieldRequirement::Hidden;

    public function __construct(
        public ContactFieldRequirement $phone,
        public ContactFieldRequirement $email,
        public ContactFieldRequirement $address,
    ) {}

    public static function defaults(): self
    {
        return new self(
            phone: self::DEFAULT_PHONE,
            email: self::DEFAULT_EMAIL,
            address: self::DEFAULT_ADDRESS,
        );
    }
}
