<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Application\Dtos\UpdateMyProfileInput;
use App\Domains\Staff\Application\Presenters\MyProfilePresenter;
use App\Domains\Staff\Application\UseCases\UpdateMyProfile;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\FakeTransactionManager;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function myProfileUpdate(array $overrides = []): array
{
    return [
        'name' => 'Ada King',
        'job_title' => 'Colorista',
        'about' => 'Especialista en rubios.',
        'phone' => ['country_code' => 'MX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new StaffJournal($this->transactions);
    $this->members = new FakeStaffMemberRepository($this->journal);
    $this->profiles = new FakeStaffProfileRepository($this->journal);
    $this->accounts = (new FakeAccountDirectory(StaffFixtures::account()))->recordingInto($this->journal);
    $this->phones = new FakeStaffPhoneBook($this->journal);
    $this->photos = new FakeStaffProfilePhotos($this->journal);
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican(), PhoneNumbers::american());

    $this->members->store(StaffFixtures::member());
    $this->profiles->store(StaffFixtures::profile());

    $this->build = fn (?FakeBusinessContext $business = null): UpdateMyProfile => new UpdateMyProfile(
        $this->members,
        $this->profiles,
        $this->accounts,
        $this->phones,
        new MyProfilePresenter($this->accounts, $this->phones, $this->photos),
        $this->parser,
        $business ?? new FakeBusinessContext,
        $this->transactions,
    );

    $this->update = fn (
        array $payload = [],
        string $accountId = StaffFixtures::ACCOUNT_ID,
        ?UpdateMyProfile $useCase = null,
    ): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(UpdateMyProfileInput::fromRequest($payload === [] ? myProfileUpdate() : $payload, $accountId));

    $this->refusal = function (array $payload = [], string $accountId = StaffFixtures::ACCOUNT_ID): UseCaseError {
        $response = ($this->update)($payload, $accountId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasWritten = function (): void {
        $stored = $this->profiles->stored(StaffFixtures::PROFILE_ID);

        expect($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->accounts->renames)->toBe([])
            ->and($this->profiles->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($stored?->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($stored?->about()?->value)->toBe(StaffFixtures::ABOUT);
    };
});

describe('updating the caller profile', function () {
    it('answers with the updated profile, field by field', function () {
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        $data = ($this->update)()->value();

        expect($data)->toBeInstanceOf(MyProfileData::class)
            ->and($data->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($data->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->name)->toBe('Ada King')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->jobTitle)->toBe('Colorista')
            ->and($data->about)->toBe('Especialista en rubios.')
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
            ->and($data->hasPassword)->toBeTrue();
    });

    it('renames the account behind the membership with the name sent', function () {
        ($this->update)(myProfileUpdate(['name' => '  Ada King  ']));

        expect($this->accounts->renames)->toBe([[
            'accountId' => StaffFixtures::ACCOUNT_ID,
            'name' => '  Ada King  ',
        ]]);
    });

    it('saves the profile with the job title and the description trimmed', function () {
        ($this->update)(myProfileUpdate(['job_title' => '  Colorista ', 'about' => "\nEspecialista en rubios.\n"]));

        $saved = $this->profiles->saved[0];

        expect($this->profiles->saved)->toHaveCount(1)
            ->and($saved->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($saved->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($saved->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($saved->jobTitle()?->value)->toBe('Colorista')
            ->and($saved->about()?->value)->toBe('Especialista en rubios.')
            ->and($saved->createdAt)->toEqual(StaffFixtures::now());
    });

    it('writes the name, the profile and the phone in that order, all inside one transaction', function () {
        ($this->update)();

        expect($this->journal->entries)->toBe(['accounts.rename', 'profiles.save', 'phones.replace'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('clears the job title and the description when they are left out', function () {
        $data = ($this->update)(['name' => 'Ada King'])->value();

        expect($this->profiles->saved[0]->jobTitle())->toBeNull()
            ->and($this->profiles->saved[0]->about())->toBeNull()
            ->and($data->jobTitle)->toBeNull()
            ->and($data->about)->toBeNull();
    });

    it('clears a field sent as null or blank', function (string $key, mixed $value) {
        ($this->update)(myProfileUpdate([$key => $value]));

        $saved = $this->profiles->saved[0];

        expect($key === 'job_title' ? $saved->jobTitle() : $saved->about())->toBeNull();
    })->with([
        'a null job title' => ['job_title', null],
        'a blank job title' => ['job_title', '   '],
        'a null description' => ['about', null],
        'a blank description' => ['about', "\n"],
    ]);
});

describe('the phone', function () {
    it('replaces the phone of the profile with the parsed number', function () {
        $this->phones->store(StaffFixtures::PROFILE_ID, PhoneNumbers::american());

        $data = ($this->update)()->value();

        expect($this->phones->replacements)->toHaveCount(1)
            ->and($this->phones->replacements[0]['profileId'])->toBe(StaffFixtures::PROFILE_ID)
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('removes the phone when none is sent', function (array $payload) {
        $this->phones->store(StaffFixtures::PROFILE_ID, PhoneNumbers::american());

        $data = ($this->update)($payload)->value();

        expect($this->phones->replacements)->toBe([['profileId' => StaffFixtures::PROFILE_ID, 'phone' => null]])
            ->and($data->phone)->toBeNull()
            ->and($this->parser->wasConsulted())->toBeFalse();
    })->with([
        'omitted' => [['name' => 'Ada King']],
        'null' => [myProfileUpdate(['phone' => null])],
        'an empty section' => [myProfileUpdate(['phone' => []])],
    ]);

    it('dials the country however it was cased or padded', function () {
        ($this->update)(myProfileUpdate(['phone' => ['country_code' => ' mx ', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER]]));

        expect($this->parser->calls())->toHaveCount(1)
            ->and($this->parser->calls()[0]['country'])->toBe(CountryCode::Mx)
            ->and($this->parser->calls()[0]['nationalNumber'])->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('hands the parser the number exactly as it was typed', function () {
        ($this->update)(myProfileUpdate(['phone' => ['country_code' => 'US', 'national_number' => ' 201 555 0123 ']]));

        expect($this->parser->calls()[0]['country'])->toBe(CountryCode::Us)
            ->and($this->parser->calls()[0]['nationalNumber'])->toBe(' 201 555 0123 ')
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('refuses a country it cannot dial without consulting the parser, writing nothing', function () {
        $error = ($this->refusal)(myProfileUpdate(['phone' => ['country_code' => 'XX', 'national_number' => '5512345678']]));

        expect($error->code)->toBe('invalid_profile_phone')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->parser->wasConsulted())->toBeFalse();

        ($this->nothingWasWritten)();
    });

    it('refuses a number the parser cannot dial, writing nothing', function () {
        $error = ($this->refusal)(myProfileUpdate(['phone' => ['country_code' => 'MX', 'national_number' => '123']]));

        expect($error->code)->toBe('invalid_profile_phone')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->parser->wasConsulted())->toBeTrue();

        ($this->nothingWasWritten)();
    });
});

describe('refusals decided before anything is written', function () {
    it('refuses the payload with the failure its first broken rule names', function (array $payload, string $accountId, string $code, DomainFailureKind $kind) {
        $error = ($this->refusal)($payload, $accountId);

        expect($error->code)->toBe($code)
            ->and($error->kind)->toBe($kind);

        ($this->nothingWasWritten)();
    })->with([
        'an account that is not a uuid' => [myProfileUpdate(), 'not-a-uuid', 'staff_member_not_found', DomainFailureKind::NotFound],
        'a missing name' => [['job_title' => 'Colorista'], StaffFixtures::ACCOUNT_ID, 'invalid_profile_name', DomainFailureKind::Invalid],
        'a whitespace-only name' => [myProfileUpdate(['name' => "  \t"]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_name', DomainFailureKind::Invalid],
        'a name past the limit' => [myProfileUpdate(['name' => str_repeat('a', 256)]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_name', DomainFailureKind::Invalid],
        'a job title past the limit' => [myProfileUpdate(['job_title' => str_repeat('a', 121)]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_job_title', DomainFailureKind::Invalid],
        'a description past the limit' => [myProfileUpdate(['about' => str_repeat('a', 1001)]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_about', DomainFailureKind::Invalid],
        'a three letter country' => [myProfileUpdate(['phone' => ['country_code' => 'MEX', 'national_number' => '5512345678']]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_phone', DomainFailureKind::Invalid],
        'a phone with no number' => [myProfileUpdate(['phone' => ['country_code' => 'MX']]), StaffFixtures::ACCOUNT_ID, 'invalid_profile_phone', DomainFailureKind::Invalid],
    ]);

    it('refuses a member with no profile, writing nothing', function () {
        $this->profiles = new FakeStaffProfileRepository($this->journal);

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});

describe('tenant isolation', function () {
    it('refuses an account whose only membership is at another business, writing nothing', function () {
        $this->members = new FakeStaffMemberRepository($this->journal);
        $this->members->store(StaffFixtures::member(businessId: StaffFixtures::OTHER_BUSINESS_ID));
        $this->profiles->store(StaffFixtures::profile(
            id: StaffFixtures::SECOND_PROFILE_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->members->accountLookups)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'accountId' => StaffFixtures::ACCOUNT_ID,
            ]]);

        ($this->nothingWasWritten)();
    });

    it('updates the profile of the business in context when the account works at two', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID));
        $this->profiles->store(StaffFixtures::profile(
            id: StaffFixtures::SECOND_PROFILE_ID,
            staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));

        $data = ($this->update)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($data->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->saved)->toHaveCount(1)
            ->and($this->profiles->saved[0]->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->saved[0]->businessId)->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and($this->phones->replacements[0]['profileId'])->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->stored(StaffFixtures::PROFILE_ID)?->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });
});

describe('refusals raised inside the transaction', function () {
    it('stops at a name the account refuses, from inside the transaction, before the profile or the phone', function () {
        $this->accounts->refuseRenameWith(InvalidProfileName::rejectedByAccount(InvalidAccountName::empty()));

        $error = ($this->refusal)();

        expect($error->code)->toBe('invalid_profile_name')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($error->cause()?->getPrevious())->toBeInstanceOf(InvalidAccountName::class)
            ->and($this->journal->entries)->toBe(['accounts.rename'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->profiles->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->profiles->stored(StaffFixtures::PROFILE_ID)?->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    });

    it('stops at an account that vanished, from inside the transaction', function () {
        $this->accounts->refuseRenameWith(StaffMemberNotFound::forAccount(StaffFixtures::ACCOUNT_ID));

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->profiles->saved)->toBe([]);
    });

    it('lets a refused profile save escape the transaction, so the rename rolls back with it, and touches no phone', function () {
        $missing = StaffMemberNotFound::withId(StaffFixtures::MEMBER_ID);
        $this->profiles->refuseSaveWith($missing);

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->cause())->toBe($missing)
            ->and($this->journal->entries)->toBe(['accounts.rename', 'profiles.save'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new RuntimeException('the staff profiles table is gone');
        $this->profiles->refuseSaveWith($bug);

        expect(fn () => ($this->update)())->toThrow($bug);
    });
});
