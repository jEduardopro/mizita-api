<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\GuestBookingNotFound;

final readonly class ShowGuestBookingInput
{
    public function __construct(
        public string $businessId,
        public GuestBookingCredentials $credentials,
    ) {}

    /**
     * @throws GuestBookingNotFound
     */
    public function validate(): void
    {
        $this->credentials->validate();
    }
}
