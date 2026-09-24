<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Application\Dtos\RemoveMyProfilePhotoInput;
use App\Domains\Staff\Application\Presenters\MyProfilePresenter;
use App\Domains\Staff\Application\UseCases\RemoveMyProfilePhoto;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(StaffFixtures::member());
    $this->profiles = (new FakeStaffProfileRepository)->store(StaffFixtures::profile());
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account());
    $this->phones = new FakeStaffPhoneBook;
    $this->photos = (new FakeStaffProfilePhotos)
        ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

    $this->build = fn (?FakeBusinessContext $business = null): RemoveMyProfilePhoto => new RemoveMyProfilePhoto(
        $this->members,
        $this->profiles,
        $this->photos,
        new MyProfilePresenter($this->accounts, $this->phones, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->remove = fn (string $accountId = StaffFixtures::ACCOUNT_ID, ?RemoveMyProfilePhoto $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new RemoveMyProfilePhotoInput($accountId));

    $this->refusal = function (string $accountId = StaffFixtures::ACCOUNT_ID): UseCaseError {
        $response = ($this->remove)($accountId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('removing the photo', function () {
    it('clears the photo of the profile under the business in context', function () {
        ($this->remove)();

        expect($this->photos->removals)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'profileId' => StaffFixtures::PROFILE_ID,
        ]]);
    });

    it('answers with the whole profile, everything but the photo untouched', function () {
        $this->phones->store(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());

        $data = ($this->remove)()->value();

        expect($data)->toBeInstanceOf(MyProfileData::class)
            ->and($data->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($data->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->photoUrl)->toBeNull()
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($data->about)->toBe(StaffFixtures::ABOUT)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('succeeds again when there is no photo left to remove', function () {
        ($this->remove)();

        $response = ($this->remove)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->photoUrl)->toBeNull()
            ->and($this->photos->removals)->toHaveCount(2);
    });

    it('writes nothing but the photo', function () {
        ($this->remove)();

        expect($this->profiles->saved)->toBe([])
            ->and($this->accounts->renames)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    });
});

describe('refusals', function () {
    it('refuses an account id that is not a uuid before looking anything up', function () {
        $error = ($this->refusal)('not-a-uuid');

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->members->accountLookups)->toBe([])
            ->and($this->photos->removals)->toBe([]);
    });

    it('refuses a member with no profile, removing nothing', function () {
        $this->profiles = new FakeStaffProfileRepository;

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->removals)->toBe([]);
    });

    it('answers with not found when the photo store has no such profile', function () {
        $this->photos = new FakeStaffProfilePhotos;

        expect(($this->refusal)()->code)->toBe('staff_profile_not_found');
    });
});

describe('tenant isolation', function () {
    it('refuses an account whose only membership is at another business, removing nothing', function () {
        $this->members = (new FakeStaffMemberRepository)->store(StaffFixtures::member(businessId: StaffFixtures::OTHER_BUSINESS_ID));

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->removals)->toBe([]);
    });

    it('removes only the photo at the business in context when the account works at two', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID));
        $this->profiles->store(StaffFixtures::profile(
            id: StaffFixtures::SECOND_PROFILE_ID,
            staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));
        $this->photos->store(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, 'https://cdn.mizita.test/staff/other.webp');

        ($this->remove)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)));

        expect($this->photos->removals)->toBe([[
            'businessId' => StaffFixtures::OTHER_BUSINESS_ID,
            'profileId' => StaffFixtures::SECOND_PROFILE_ID,
        ]])
            ->and($this->photos->urlFor(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID))->toBe(StaffFixtures::PHOTO_URL);
    });
});
