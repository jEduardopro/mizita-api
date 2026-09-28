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

    public function changesAnythingOf(self $current): bool
    {
        return $this->phone !== $current->phone
            || $this->email !== $current->email
            || $this->address !== $current->address;
    }
}
