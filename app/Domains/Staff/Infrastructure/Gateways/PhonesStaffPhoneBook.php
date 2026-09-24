<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Shared\ValueObjects\PhoneNumber;

final class PhonesStaffPhoneBook implements StaffPhoneBook
{
    public function __construct(
        private readonly AttachPhone $attachPhone,
        private readonly PhoneRepository $phones,
    ) {}

    public function forProfile(string $profileId): ?PhoneNumber
    {
        return $this->phones->findForOwner(PhoneOwnerType::StaffProfile, $profileId)?->number();
    }

    public function replaceForProfile(string $profileId, ?PhoneNumber $phone): void
    {
        if ($phone === null) {
            $this->phones->deleteForOwner(PhoneOwnerType::StaffProfile, $profileId);

            return;
        }

        $this->attachPhone->handle(new AttachPhoneInput(
            ownerType: PhoneOwnerType::StaffProfile,
            ownerId: $profileId,
            number: $phone,
        ))->value();
    }
}
