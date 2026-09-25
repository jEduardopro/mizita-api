<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberPhotoInput;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\RemoveTeamMemberPhoto;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
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
    $this->profiles = (new FakeStaffProfileRepository)->store(
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
        StaffFixtures::profile(id: StaffFixtures::THIRD_PROFILE_ID, staffMemberId: StaffFixtures::THIRD_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID),
    );
    $this->photos = (new FakeStaffProfilePhotos)
        ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, StaffFixtures::PHOTO_URL)
        ->store(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::THIRD_PROFILE_ID, 'https://cdn.mizita.test/staff/elsewhere.webp');
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'));

    $this->build = fn (?FakeBusinessContext $business = null): RemoveTeamMemberPhoto => new RemoveTeamMemberPhoto(
        $this->members,
        $this->profiles,
        $this->photos,
        new TeamMemberPresenter($this->accounts, $this->profiles, new FakeStaffPhoneBook, $this->photos, new FakeTeamTemporaryPasswords),
        $business ?? new FakeBusinessContext,
    );

    $this->remove = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?RemoveTeamMemberPhoto $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new RemoveTeamMemberPhotoInput($staffMemberId));
});

it('removes the photo of the member and answers with the member, now without one', function () {
    $data = ($this->remove)()->value();

    expect($this->photos->removals)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'profileId' => StaffFixtures::SECOND_PROFILE_ID]])
        ->and($data->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($data->name)->toBe('Grace Hopper')
        ->and($data->photoUrl)->toBeNull();
});

it('refuses a value that is not a uuid without looking anything up', function () {
    $response = ($this->remove)('42');

    expect($response->error()->code)->toBe('staff_member_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->members->businessLookups)->toBe([])
        ->and($this->photos->removals)->toBe([]);
});

it('refuses a member with no profile without touching any photo', function () {
    $this->profiles = new FakeStaffProfileRepository;

    $response = ($this->remove)();

    expect($response->error()->code)->toBe('staff_profile_not_found')
        ->and($this->photos->removals)->toBe([]);
});

it('lets a presenter refusal after the removal escape, rather than refusing a photo that is already gone', function () {
    $this->accounts = new FakeAccountDirectory;

    expect(fn () => ($this->remove)())->toThrow(StaffMemberNotFound::class)
        ->and($this->photos->removals)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'profileId' => StaffFixtures::SECOND_PROFILE_ID]])
        ->and($this->photos->urlFor(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID))->toBeNull();
});

describe('tenant isolation', function () {
    it('refuses a member of another business and leaves its photo in place', function () {
        $response = ($this->remove)(StaffFixtures::THIRD_MEMBER_ID);

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($this->photos->removals)->toBe([])
            ->and($this->photos->urlFor(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::THIRD_PROFILE_ID))->toBe('https://cdn.mizita.test/staff/elsewhere.webp');
    });

    it('removes the photo of the business in context only', function () {
        ($this->remove)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($this->photos->removals)->toBe([['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'profileId' => StaffFixtures::THIRD_PROFILE_ID]])
            ->and($this->photos->urlFor(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID))->toBe(StaffFixtures::PHOTO_URL);
    });
});
