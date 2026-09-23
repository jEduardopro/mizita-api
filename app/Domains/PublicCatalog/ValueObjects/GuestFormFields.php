<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use App\Domains\PublicCatalog\Exceptions\MissingGuestContactField;

final readonly class GuestFormFields
{
    public function __construct(
        public GuestFieldRequirement $phone,
        public GuestFieldRequirement $email,
        public GuestFieldRequirement $address,
    ) {}

    /**
     * @throws MissingGuestContactField
     */
    public function applyTo(PublicGuestDetails $guest): PublicGuestDetails
    {
        return $this->collectAddressFrom(
            $this->collectEmailFrom(
                $this->collectPhoneFrom($guest),
            ),
        );
    }

    private function collectPhoneFrom(PublicGuestDetails $guest): PublicGuestDetails
    {
        if (! $this->phone->isCollected()) {
            return $guest->withoutPhone();
        }

        if ($this->phone->isRequired() && ! $guest->hasPhone()) {
            throw MissingGuestContactField::phone();
        }

        return $guest;
    }

    private function collectEmailFrom(PublicGuestDetails $guest): PublicGuestDetails
    {
        if (! $this->email->isCollected()) {
            return $guest->withoutEmail();
        }

        if ($this->email->isRequired() && ! $guest->hasEmail()) {
            throw MissingGuestContactField::email();
        }

        return $guest;
    }

    private function collectAddressFrom(PublicGuestDetails $guest): PublicGuestDetails
    {
        if (! $this->address->isCollected()) {
            return $guest->withoutAddress();
        }

        if ($guest->hasCompleteAddress()) {
            return $guest;
        }

        if ($this->address->isRequired() || ! $guest->hasBlankAddress()) {
            throw MissingGuestContactField::address();
        }

        return $guest->withoutAddress();
    }
}
