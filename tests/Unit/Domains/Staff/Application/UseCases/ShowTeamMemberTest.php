<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ShowTeamMemberInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\ShowTeamMember;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeTeamTemporaryPasswords;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com', awaitingPasswordChange: true),
    );
    $this->profiles = (new FakeStaffProfileRepository)->store(
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
    );
    $this->phones = (new FakeStaffPhoneBook)->store(StaffFixtures::SECOND_PROFILE_ID, PhoneNumbers::american());
    $this->photos = (new FakeStaffProfilePhotos)->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, StaffFixtures::PHOTO_URL);
    $this->temporaryPasswords = (new FakeTeamTemporaryPasswords)->holds(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);

    $this->build = fn (?FakeBusinessContext $business = null): ShowTeamMember => new ShowTeamMember(
        $this->members,
        new TeamMemberPresenter($this->accounts, $this->profiles, $this->phones, $this->photos, $this->temporaryPasswords),
        $business ?? new FakeBusinessContext,
    );

    $this->show = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?ShowTeamMember $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new ShowTeamMemberInput($staffMemberId));
});

it('answers with the member, field by field', function () {
    $data = ($this->show)()->value();

    expect($data)->toBeInstanceOf(TeamMemberData::class)
        ->and($data->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($data->name)->toBe('Grace Hopper')
        ->and($data->email)->toBe('grace@example.com')
        ->and($data->phone?->e164())->toBe(PhoneNumbers::US_E164)
        ->and($data->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
        ->and($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
        ->and($data->about)->toBe(StaffFixtures::ABOUT)
        ->and($data->level)->toBe(StaffRole::Member)
        ->and($data->invitationPending)->toBeTrue()
        ->and($data->temporaryPasswordAvailable)->toBeTrue()
        ->and($data->createdAt)->toEqual(StaffFixtures::now());
});

it('refuses a value that is not a uuid without looking anything up', function () {
    $response = ($this->show)('42');

    expect($response->error()->code)->toBe('staff_member_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->members->businessLookups)->toBe([]);
});

it('refuses a member nobody has', function () {
    expect(($this->show)(StaffFixtures::MEMBER_ID)->error()->code)->toBe('staff_member_not_found');
});

it('refuses a member whose account is gone', function () {
    $this->accounts = new FakeAccountDirectory;

    expect(($this->show)()->error()->code)->toBe('staff_member_not_found');
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $response = ($this->show)(StaffFixtures::THIRD_MEMBER_ID);

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]]);
    });

    it('shows that member when its business is the one in context, without anything filed here', function () {
        $data = ($this->show)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($data->id)->toBe(StaffFixtures::THIRD_MEMBER_ID)
            ->and($data->jobTitle)->toBeNull()
            ->and($data->photoUrl)->toBeNull();
    });
});
