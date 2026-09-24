<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Application\Dtos\ShowMyProfileInput;
use App\Domains\Staff\Application\Presenters\MyProfilePresenter;
use App\Domains\Staff\Application\UseCases\ShowMyProfile;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
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
    $this->members = new FakeStaffMemberRepository;
    $this->profiles = new FakeStaffProfileRepository;
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account());
    $this->phones = new FakeStaffPhoneBook;
    $this->photos = new FakeStaffProfilePhotos;

    $this->build = fn (?FakeBusinessContext $business = null): ShowMyProfile => new ShowMyProfile(
        $this->members,
        $this->profiles,
        new MyProfilePresenter($this->accounts, $this->phones, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->show = fn (string $accountId = StaffFixtures::ACCOUNT_ID, ?ShowMyProfile $useCase = null) => ($useCase ?? ($this->build)())
        ->handle(new ShowMyProfileInput($accountId));

    $this->refusal = function (string $accountId = StaffFixtures::ACCOUNT_ID, ?ShowMyProfile $useCase = null): UseCaseError {
        $response = ($this->show)($accountId, $useCase);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('showing the caller their own profile', function () {
    beforeEach(function () {
        $this->members->store(StaffFixtures::member());
        $this->profiles->store(StaffFixtures::profile());
    });

    it('answers with the whole profile, field by field', function () {
        $this->phones->store(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(MyProfileData::class)
            ->and($data->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($data->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($data->about)->toBe(StaffFixtures::ABOUT)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
            ->and($data->role)->toBe(StaffRole::Owner)
            ->and($data->hasPassword)->toBeTrue();
    });

    it('identifies the profile and its member by uuid, never by the account behind them', function () {
        $data = ($this->show)()->value();

        expect($data->id)->not->toBe($data->staffMemberId)
            ->and($data->id)->not->toBe(StaffFixtures::ACCOUNT_ID)
            ->and($data->staffMemberId)->not->toBe(StaffFixtures::ACCOUNT_ID)
            ->and(is_numeric($data->id))->toBeFalse()
            ->and(is_numeric($data->staffMemberId))->toBeFalse();
    });

    it('tells an account that signs in only through google that it has no password', function () {
        $this->accounts = new FakeAccountDirectory(StaffFixtures::account(hasPassword: false));

        expect(($this->show)()->value()->hasPassword)->toBeFalse();
    });

    it('answers with nulls for a profile that never described itself, has no phone and no photo', function () {
        $this->profiles->store(StaffFixtures::profile(jobTitle: null, about: null));

        $data = ($this->show)()->value();

        expect($data->jobTitle)->toBeNull()
            ->and($data->about)->toBeNull()
            ->and($data->phone)->toBeNull()
            ->and($data->photoUrl)->toBeNull();
    });

    it('carries the role the membership holds', function () {
        $this->members->store(StaffFixtures::member(role: StaffRole::Member));

        expect(($this->show)()->value()->role)->toBe(StaffRole::Member);
    });

    it('reads the phone of the profile and the photo under the business in context', function () {
        ($this->show)();

        expect($this->phones->reads)->toBe([StaffFixtures::PROFILE_ID])
            ->and($this->photos->reads)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'profileId' => StaffFixtures::PROFILE_ID,
            ]])
            ->and($this->accounts->calls)->toBe([[StaffFixtures::ACCOUNT_ID]]);
    });

    it('writes nothing', function () {
        ($this->show)();

        expect($this->profiles->saved)->toBe([])
            ->and($this->members->saved)->toBe([])
            ->and($this->accounts->renames)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('looks the membership up under the business in context', function () {
        $this->members->store(StaffFixtures::member());
        $this->profiles->store(StaffFixtures::profile());

        ($this->show)();

        expect($this->members->accountLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => StaffFixtures::ACCOUNT_ID,
        ]])
            ->and($this->profiles->lookups)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'staffMemberId' => StaffFixtures::MEMBER_ID,
            ]]);
    });

    it('refuses an account whose only membership is at another business', function () {
        $this->members->store(StaffFixtures::member(businessId: StaffFixtures::OTHER_BUSINESS_ID));
        $this->profiles->store(StaffFixtures::profile(businessId: StaffFixtures::OTHER_BUSINESS_ID));

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->profiles->lookups)->toBe([]);
    });

    it('shows the profile of the business in context when the account works at two', function () {
        $this->members->store(
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
        );
        $this->profiles->store(
            StaffFixtures::profile(),
            StaffFixtures::profile(
                id: StaffFixtures::SECOND_PROFILE_ID,
                staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
                businessId: StaffFixtures::OTHER_BUSINESS_ID,
                jobTitle: 'Recepcionista',
            ),
        );

        $data = ($this->show)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($data->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($data->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($data->jobTitle)->toBe('Recepcionista')
            ->and($data->role)->toBe(StaffRole::Member);
    });

    it('refuses a profile filed under another business than the membership', function () {
        $this->members->store(StaffFixtures::member());
        $this->profiles->store(StaffFixtures::profile(businessId: StaffFixtures::OTHER_BUSINESS_ID));

        expect(($this->refusal)()->code)->toBe('staff_profile_not_found');
    });
});

describe('refusals', function () {
    it('refuses an account id that is not a uuid before looking anything up', function (string $accountId) {
        $error = ($this->refusal)($accountId);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->members->accountLookups)->toBe([]);
    })->with([
        'empty' => '',
        'an int id' => '1',
    ]);

    it('refuses a member with no profile', function () {
        $this->members->store(StaffFixtures::member());

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('refuses a member whose account the directory cannot describe', function () {
        $this->members->store(StaffFixtures::member());
        $this->profiles->store(StaffFixtures::profile());
        $this->accounts = new FakeAccountDirectory;

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound);
    });
});
