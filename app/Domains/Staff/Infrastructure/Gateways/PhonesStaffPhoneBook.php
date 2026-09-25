<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Phones\Application\Dtos\AttachPhoneInput;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
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

    /**
     * @param  list<string>  $profileIds
     * @return array<string, PhoneNumber>
     */
    public function forProfiles(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        return array_map(
            static fn (Phone $phone): PhoneNumber => $phone->number(),
            $this->phones->findForOwners(PhoneOwnerType::StaffProfile, $profileIds),
        );
    }

    /**
     * @return list<string>
     */
    public function profileIdsMatchingNumber(string $fragment): array
    {
        $digits = PhoneNumberFragment::of($fragment);

        if ($digits === null) {
            return [];
        }

        return $this->phones->ownerIdsMatchingNumber(PhoneOwnerType::StaffProfile, $digits);
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
