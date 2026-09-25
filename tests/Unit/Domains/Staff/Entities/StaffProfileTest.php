<?php

declare(strict_types=1);

use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

describe('create', function () {
    it('creates a blank profile for a staff member of a business', function () {
        $profile = StaffProfile::create(
            id: StaffFixtures::PROFILE_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: StaffFixtures::MEMBER_ID,
            now: StaffFixtures::now(),
        );

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull()
            ->and($profile->createdAt)->toEqual(StaffFixtures::now());
    });

    it('keeps its own identity apart from the staff member it describes', function () {
        $profile = StaffProfile::create(StaffFixtures::PROFILE_ID, FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID, StaffFixtures::now());

        expect($profile->id)->not->toBe($profile->staffMemberId);
    });
});

describe('restore', function () {
    it('rehydrates a profile exactly as it was stored', function () {
        $createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');

        $profile = StaffProfile::restore(
            id: StaffFixtures::PROFILE_ID,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: StaffFixtures::MEMBER_ID,
            jobTitle: JobTitle::restore(StaffFixtures::JOB_TITLE),
            about: About::restore(StaffFixtures::ABOUT),
            createdAt: $createdAt,
        );

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT)
            ->and($profile->createdAt)->toEqual($createdAt);
    });

    it('rehydrates a profile that never described itself', function () {
        $profile = StaffFixtures::profile(jobTitle: null, about: null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull();
    });
});

describe('describe', function () {
    it('takes the job title and the description it is handed', function () {
        $profile = StaffFixtures::profile(jobTitle: null, about: null);

        $profile->describe(JobTitle::fromNullable('Colorista'), About::fromNullable('Especialista en rubios.'));

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about()?->value)->toBe('Especialista en rubios.');
    });

    it('clears both when handed nothing', function () {
        $profile = StaffFixtures::profile();

        $profile->describe(null, null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull();
    });

    it('replaces each field on its own, so clearing one does not keep the other', function () {
        $profile = StaffFixtures::profile();

        $profile->describe(JobTitle::fromNullable('Colorista'), null);

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about())->toBeNull();
    });

    it('leaves the identity, the business and the creation instant untouched', function () {
        $profile = StaffFixtures::profile();

        $profile->describe(null, null);

        expect($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->createdAt)->toEqual(StaffFixtures::now());
    });
});

describe('changing one field at a time', function () {
    it('changes the job title and keeps the description', function () {
        $profile = StaffFixtures::profile();

        $profile->changeJobTitle(JobTitle::fromNullable('Colorista'));

        expect($profile->jobTitle()?->value)->toBe('Colorista')
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT);
    });

    it('clears the job title and keeps the description', function () {
        $profile = StaffFixtures::profile();

        $profile->changeJobTitle(null);

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT);
    });

    it('changes the description and keeps the job title', function () {
        $profile = StaffFixtures::profile();

        $profile->changeAbout(About::fromNullable('Especialista en rubios.'));

        expect($profile->about()?->value)->toBe('Especialista en rubios.')
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });

    it('clears the description and keeps the job title', function () {
        $profile = StaffFixtures::profile();

        $profile->changeAbout(null);

        expect($profile->about())->toBeNull()
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });
});
