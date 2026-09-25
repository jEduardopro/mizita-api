<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;

final readonly class TeamMemberData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?PhoneNumber $phone,
        public ?string $photoUrl,
        public ?string $jobTitle,
        public ?string $about,
        public StaffRole $level,
        public bool $invitationPending,
        public bool $temporaryPasswordAvailable,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntities(
        StaffMember $member,
        ?StaffProfile $profile,
        AccountSnapshot $account,
        ?PhoneNumber $phone,
        ?string $photoUrl,
        bool $holdsTemporaryPassword,
    ): self {
        $invitationPending = $member->hasPendingInvitation($account);

        return new self(
            id: $member->id,
            name: $account->name,
            email: $account->email,
            phone: $phone,
            photoUrl: $photoUrl,
            jobTitle: $profile?->jobTitle()?->value,
            about: $profile?->about()?->value,
            level: $member->role(),
            invitationPending: $invitationPending,
            temporaryPasswordAvailable: $invitationPending && $holdsTemporaryPassword,
            createdAt: $member->createdAt,
        );
    }
}
