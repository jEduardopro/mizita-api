<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

final class PhonesPhoneBook implements PhoneBook
{
    public function __construct(
        private readonly AttachPhone $attachPhone,
    ) {}

    public function attachToBusiness(string $businessId, PhoneNumber $phone): void
    {
        $this->attachPhone->handle(new AttachPhoneInput(
            ownerType: PhoneOwnerType::Business,
            ownerId: $businessId,
            number: $phone,
        ))->value();
    }
}
