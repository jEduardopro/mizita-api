<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Application\Presenters\BookingLinkPresenter;
use App\Domains\Staff\Application\Services\BookingReadinessAssessor;
use App\Domains\Staff\Contracts\BusinessSlugs;
use App\Domains\Staff\Contracts\ServiceAssignments;
use App\Domains\Staff\Contracts\WorkingHours;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Services\BookingLinks;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Domains\Staff\ValueObjects\JobTitle;
use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class StaffFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_MEMBER_ID = '01930000-0000-7000-8000-0000000000d2';

    public const THIRD_MEMBER_ID = '01930000-0000-7000-8000-0000000000d3';

    public const ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const SECOND_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a2';

    public const THIRD_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a3';

    public const PROFILE_ID = '01930000-0000-7000-8000-0000000000e1';

    public const SECOND_PROFILE_ID = '01930000-0000-7000-8000-0000000000e2';

    public const THIRD_PROFILE_ID = '01930000-0000-7000-8000-0000000000e3';

    public const TEMPORARY_PASSWORD = 'Tmp-Pa55word!';

    public const BUSINESS_NAME = 'Barbería Ñuñoa';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const THIRD_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b3';

    public const FOURTH_MEMBER_ID = '01930000-0000-7000-8000-0000000000d4';

    public const JOB_TITLE = 'Barbera principal';

    public const ABOUT = 'Diez años cortando el pelo en Ñuñoa.';

    public const PHOTO_URL = 'https://cdn.mizita.test/staff/ada.webp';

    public const BUSINESS_SLUG = 'barberia-nunoa';

    public const BOOKING_BASE_URL = 'https://mizita.test';

    public const BOOKING_SLUG = 'ada-lovelace';

    public const BOOKING_URL = self::BOOKING_BASE_URL.'/'.self::BUSINESS_SLUG.'/equipo/'.self::BOOKING_SLUG;

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function bookingLinkPresenter(
        ServiceAssignments $services = new FakeServiceAssignments,
        WorkingHours $workingHours = new FakeWorkingHours,
        BusinessSlugs $businesses = new FakeBusinessSlugs,
    ): BookingLinkPresenter {
        return new BookingLinkPresenter(
            new BookingReadinessAssessor($services, $workingHours),
            $businesses,
            new BookingLinks(self::BOOKING_BASE_URL),
        );
    }

    public static function member(
        string $id = self::MEMBER_ID,
        string $accountId = self::ACCOUNT_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        StaffRole $role = StaffRole::Owner,
        ?DateTimeImmutable $createdAt = null,
    ): StaffMember {
        return StaffMember::restore(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: $role,
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function profile(
        string $id = self::PROFILE_ID,
        string $staffMemberId = self::MEMBER_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        ?string $jobTitle = self::JOB_TITLE,
        ?string $about = self::ABOUT,
        ?DateTimeImmutable $createdAt = null,
        ?string $bookingSlug = null,
    ): StaffProfile {
        return StaffProfile::restore(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            jobTitle: $jobTitle === null ? null : JobTitle::restore($jobTitle),
            about: $about === null ? null : About::restore($about),
            bookingSlug: $bookingSlug === null ? null : BookingSlug::restore($bookingSlug),
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function account(
        string $id = self::ACCOUNT_ID,
        string $name = 'Ada Lovelace',
        string $email = 'ada@example.com',
        bool $hasPassword = true,
        bool $awaitingPasswordChange = false,
    ): AccountSnapshot {
        return new AccountSnapshot($id, $name, $email, $hasPassword, $awaitingPasswordChange);
    }
}
