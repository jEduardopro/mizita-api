<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

final class PhonesPhoneBook implements PhoneBook
{
    public function __construct(
        private readonly AttachPhone $attachPhone,
        private readonly PhoneRepository $phones,
    ) {}

    public function forBusiness(string $businessId): ?PhoneNumber
    {
        return $this->phones->findForOwner(PhoneOwnerType::Business, $businessId)?->number();
    }

    public function attachToBusiness(string $businessId, PhoneNumber $phone): void
    {
        $this->attachPhone->handle(new AttachPhoneInput(
            ownerType: PhoneOwnerType::Business,
            ownerId: $businessId,
            number: $phone,
        ))->value();
    }

    public function replaceForBusiness(string $businessId, ?PhoneNumber $phone): void
    {
        if ($phone === null) {
            $this->phones->deleteForOwner(PhoneOwnerType::Business, $businessId);

            return;
        }

        $this->attachToBusiness($businessId, $phone);
    }
}
