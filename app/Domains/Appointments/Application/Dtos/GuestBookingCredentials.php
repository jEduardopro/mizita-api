<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\Appointments\Exceptions\InvalidManageToken;
use App\Domains\Appointments\Exceptions\InvalidReferenceCode;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ReferenceCode;

final readonly class GuestBookingCredentials
{
    public function __construct(
        public string $referenceCode,
        public string $manageToken,
    ) {}

    /**
     * @throws GuestBookingNotFound
     */
    public function validate(): void
    {
        try {
            ReferenceCode::fromString($this->referenceCode);
            ManageToken::fromString($this->manageToken);
        } catch (InvalidReferenceCode|InvalidManageToken $malformed) {
            throw GuestBookingNotFound::forCredentials($malformed);
        }
    }

    /**
     * @throws GuestBookingNotFound
     */
    public function toReferenceCode(): string
    {
        try {
            return ReferenceCode::fromString($this->referenceCode)->value;
        } catch (InvalidReferenceCode $malformed) {
            throw GuestBookingNotFound::forCredentials($malformed);
        }
    }
}
