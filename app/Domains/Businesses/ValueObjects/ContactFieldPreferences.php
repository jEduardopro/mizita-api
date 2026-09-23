<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class ContactFieldPreferences
{
    public function __construct(
        public ContactFieldPreference $phone,
        public ContactFieldPreference $email,
        public ContactFieldPreference $address,
    ) {}
}
