<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\ValueObjects\CustomerPhoneSnapshot;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;

final readonly class AppointmentCustomerData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?CustomerPhoneSnapshot $phone,
    ) {}

    public static function fromSnapshot(CustomerSnapshot $customer): self
    {
        return new self(
            id: $customer->id,
            name: $customer->name,
            email: $customer->email,
            phone: $customer->phone,
        );
    }
}
