<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\ValueObjects\PhoneNumber;

final readonly class MyProfileData
{
    public function __construct(
        public string $id,
        public string $staffMemberId,
        public string $name,
        public string $email,
        public ?string $jobTitle,
        public ?string $about,
        public ?PhoneNumber $phone,
        public ?string $photoUrl,
        public BookingLinkStatus $bookingLink,
        public StaffRole $role,
        public bool $hasPassword,
    ) {}

    public static function fromEntities(
        StaffMember $member,
        StaffProfile $profile,
        AccountSnapshot $account,
        ?PhoneNumber $phone,
        ?string $photoUrl,
        BookingLinkStatus $bookingLink,
    ): self {
        return new self(
            id: $profile->id,
            staffMemberId: $member->id,
            name: $account->name,
            email: $account->email,
            jobTitle: $profile->jobTitle()?->value,
            about: $profile->about()?->value,
            phone: $phone,
            photoUrl: $photoUrl,
            bookingLink: $bookingLink,
            role: $member->role(),
            hasPassword: $account->hasPassword,
        );
    }
}
