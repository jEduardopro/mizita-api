<?php

declare(strict_types=1);

use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Infrastructure\Eloquent\Mappers\StaffProfileMapper;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

const STAFF_PROFILE_MAPPER_BUSINESS_KEY = 42;

const STAFF_PROFILE_MAPPER_MEMBER_KEY = 17;

/**
 * @param  array<string, mixed>  $overrides
 */
function mappedStaffProfileRow(array $overrides = []): StaffProfileModel
{
    $model = new StaffProfileModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => StaffFixtures::PROFILE_ID,
        'business_id' => STAFF_PROFILE_MAPPER_BUSINESS_KEY,
        'staff_member_id' => STAFF_PROFILE_MAPPER_MEMBER_KEY,
        'job_title' => StaffFixtures::JOB_TITLE,
        'about' => StaffFixtures::ABOUT,
        'created_at' => StaffFixtures::now(),
        ...$overrides,
    ], true);

    return $model;
}

beforeEach(function () {
    $this->mapper = new StaffProfileMapper;
});

describe('writing a row', function () {
    it('writes exactly the columns the row owns', function () {
        expect($this->mapper->toAttributes(StaffFixtures::profile(), STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY))->toBe([
            'uuid' => StaffFixtures::PROFILE_ID,
            'business_id' => STAFF_PROFILE_MAPPER_BUSINESS_KEY,
            'staff_member_id' => STAFF_PROFILE_MAPPER_MEMBER_KEY,
            'job_title' => StaffFixtures::JOB_TITLE,
            'about' => StaffFixtures::ABOUT,
        ]);
    });

    it('writes the business and the member as the int keys it was handed, never as the uuids the entity carries', function () {
        $attributes = $this->mapper->toAttributes(StaffFixtures::profile(), STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY);

        expect($attributes['business_id'])->toBeInt()->toBe(STAFF_PROFILE_MAPPER_BUSINESS_KEY)
            ->and($attributes['staff_member_id'])->toBeInt()->toBe(STAFF_PROFILE_MAPPER_MEMBER_KEY)
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID)
            ->and($attributes)->not->toContain(StaffFixtures::MEMBER_ID);
    });

    it('writes the profile uuid, never an int, as the public identity', function () {
        expect($this->mapper->toAttributes(StaffFixtures::profile(), STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY)['uuid'])
            ->toBe(StaffFixtures::PROFILE_ID)
            ->and($this->mapper->toAttributes(StaffFixtures::profile(), STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY))
            ->not->toHaveKey('id');
    });

    it('writes null for a profile that never described itself, so a cleared field is cleared in the row', function () {
        $attributes = $this->mapper->toAttributes(
            StaffFixtures::profile(jobTitle: null, about: null),
            STAFF_PROFILE_MAPPER_BUSINESS_KEY,
            STAFF_PROFILE_MAPPER_MEMBER_KEY,
        );

        expect($attributes)->toHaveKey('job_title')
            ->and($attributes['job_title'])->toBeNull()
            ->and($attributes)->toHaveKey('about')
            ->and($attributes['about'])->toBeNull();
    });

    it('leaves the timestamps to eloquent', function () {
        expect($this->mapper->toAttributes(StaffFixtures::profile(), STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY))
            ->not->toHaveKey('created_at')
            ->not->toHaveKey('updated_at');
    });
});

describe('reading a row', function () {
    it('rehydrates the profile under the uuids it was handed', function () {
        $profile = $this->mapper->toEntity(mappedStaffProfileRow(), FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($profile)->toBeInstanceOf(StaffProfile::class)
            ->and($profile->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($profile->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($profile->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($profile->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($profile->about()?->value)->toBe(StaffFixtures::ABOUT)
            ->and($profile->createdAt)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($profile->createdAt->getTimestamp())->toBe(StaffFixtures::now()->getTimestamp());
    });

    it('never lets the int keys of the row become the identities of the entity', function () {
        $profile = $this->mapper->toEntity(mappedStaffProfileRow(), FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($profile->id)->not->toBe('7')
            ->and($profile->businessId)->not->toBe((string) STAFF_PROFILE_MAPPER_BUSINESS_KEY)
            ->and($profile->staffMemberId)->not->toBe((string) STAFF_PROFILE_MAPPER_MEMBER_KEY);
    });

    it('reads null columns as a profile that never described itself', function () {
        $profile = $this->mapper->toEntity(
            mappedStaffProfileRow(['job_title' => null, 'about' => null]),
            FakeBusinessContext::BUSINESS_ID,
            StaffFixtures::MEMBER_ID,
        );

        expect($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull();
    });

    it('loads a stored value the creation rules would now refuse, untouched', function () {
        $legacyTitle = str_repeat('a', 130);

        $profile = $this->mapper->toEntity(
            mappedStaffProfileRow(['job_title' => $legacyTitle, 'about' => '  padded  ']),
            FakeBusinessContext::BUSINESS_ID,
            StaffFixtures::MEMBER_ID,
        );

        expect($profile->jobTitle()?->value)->toBe($legacyTitle)
            ->and($profile->about()?->value)->toBe('  padded  ');
    });

    it('round trips a profile through a row without losing anything', function () {
        $original = StaffFixtures::profile();
        $attributes = $this->mapper->toAttributes($original, STAFF_PROFILE_MAPPER_BUSINESS_KEY, STAFF_PROFILE_MAPPER_MEMBER_KEY);

        $restored = $this->mapper->toEntity(
            mappedStaffProfileRow([...$attributes, 'created_at' => $original->createdAt]),
            $original->businessId,
            $original->staffMemberId,
        );

        expect($restored->id)->toBe($original->id)
            ->and($restored->jobTitle()?->value)->toBe($original->jobTitle()?->value)
            ->and($restored->about()?->value)->toBe($original->about()?->value);
    });
});
