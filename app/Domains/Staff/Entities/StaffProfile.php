<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use DateTimeImmutable;

final class StaffProfile
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $staffMemberId,
        private ?JobTitle $jobTitle,
        private ?About $about,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $businessId,
        string $staffMemberId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: null,
            about: null,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $staffMemberId,
        ?JobTitle $jobTitle,
        ?About $about,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: $jobTitle,
            about: $about,
            createdAt: $createdAt,
        );
    }

    public function describe(?JobTitle $jobTitle, ?About $about): void
    {
        $this->jobTitle = $jobTitle;
        $this->about = $about;
    }

    public function jobTitle(): ?JobTitle
    {
        return $this->jobTitle;
    }

    public function about(): ?About
    {
        return $this->about;
    }
}
