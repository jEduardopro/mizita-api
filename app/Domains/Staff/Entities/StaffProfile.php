<?php

declare(strict_types=1);

namespace App\Domains\Staff\Entities;

use App\Domains\Staff\Exceptions\BookingLinkAlreadyExists;
use App\Domains\Staff\Exceptions\BookingLinkNotFound;
use App\Domains\Staff\Exceptions\StaffMemberCannotReceiveBookings;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\BookingReadiness;
use App\Domains\Staff\ValueObjects\BookingSlug;
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
        private ?BookingSlug $bookingSlug,
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
            bookingSlug: null,
            createdAt: $now,
        );
    }

    public static function createForOwner(
        string $id,
        string $businessId,
        string $staffMemberId,
        BookingSlug $bookingSlug,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: null,
            about: null,
            bookingSlug: $bookingSlug,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $staffMemberId,
        ?JobTitle $jobTitle,
        ?About $about,
        ?BookingSlug $bookingSlug,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: $jobTitle,
            about: $about,
            bookingSlug: $bookingSlug,
            createdAt: $createdAt,
        );
    }

    public function describe(?JobTitle $jobTitle, ?About $about): void
    {
        $this->jobTitle = $jobTitle;
        $this->about = $about;
    }

    public function changeJobTitle(?JobTitle $jobTitle): void
    {
        $this->jobTitle = $jobTitle;
    }

    public function changeAbout(?About $about): void
    {
        $this->about = $about;
    }

    /**
     * @throws BookingLinkAlreadyExists
     * @throws StaffMemberCannotReceiveBookings
     */
    public function assignBookingSlug(BookingSlug $bookingSlug, BookingReadiness $readiness): void
    {
        if ($this->bookingSlug !== null) {
            throw BookingLinkAlreadyExists::forStaffMember($this->staffMemberId);
        }

        if (! $readiness->allowsBookingLink()) {
            throw StaffMemberCannotReceiveBookings::forStaffMember($this->staffMemberId);
        }

        $this->bookingSlug = $bookingSlug;
    }

    /**
     * @throws BookingLinkNotFound
     */
    public function changeBookingSlug(BookingSlug $bookingSlug): void
    {
        if ($this->bookingSlug === null) {
            throw BookingLinkNotFound::forStaffMember($this->staffMemberId);
        }

        $this->bookingSlug = $bookingSlug;
    }

    public function jobTitle(): ?JobTitle
    {
        return $this->jobTitle;
    }

    public function about(): ?About
    {
        return $this->about;
    }

    public function bookingSlug(): ?BookingSlug
    {
        return $this->bookingSlug;
    }
}
