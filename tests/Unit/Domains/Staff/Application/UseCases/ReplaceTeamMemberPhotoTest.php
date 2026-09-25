<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ReplaceTeamMemberPhotoInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\ReplaceTeamMemberPhoto;
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

function teamMemberPhotoUpload(
    string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID,
    string $mimeType = 'image/webp',
    int $sizeInBytes = 2048,
): ReplaceTeamMemberPhotoInput {
    return new ReplaceTeamMemberPhotoInput($staffMemberId, '/tmp/php-upload-1', 'grace.webp', $mimeType, $sizeInBytes);
}

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
        ->knows(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID)
        ->knows(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::THIRD_PROFILE_ID);
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'));

    $this->build = fn (?FakeBusinessContext $business = null): ReplaceTeamMemberPhoto => new ReplaceTeamMemberPhoto(
        $this->members,
        $this->profiles,
        $this->photos,
        new TeamMemberPresenter($this->accounts, $this->profiles, new FakeStaffPhoneBook, $this->photos, new FakeTeamTemporaryPasswords),
        $business ?? new FakeBusinessContext,
    );

    $this->replace = fn (?ReplaceTeamMemberPhotoInput $input = null, ?ReplaceTeamMemberPhoto $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle($input ?? teamMemberPhotoUpload());
});

it('files the upload under the profile of the member and answers with the new photo', function () {
    $data = ($this->replace)()->value();

    expect($this->photos->replacements)->toBe([[
        'businessId' => FakeBusinessContext::BUSINESS_ID,
        'profileId' => StaffFixtures::SECOND_PROFILE_ID,
        'sourcePath' => '/tmp/php-upload-1',
        'fileName' => 'grace.webp',
    ]])
        ->and($data)->toBeInstanceOf(TeamMemberData::class)
        ->and($data->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($data->photoUrl)->toBe(FakeStaffProfilePhotos::urlOf(StaffFixtures::SECOND_PROFILE_ID, 'grace.webp'));
});

it('refuses an upload the input rejects without touching any photo', function (ReplaceTeamMemberPhotoInput $input, string $code, DomainFailureKind $kind) {
    $response = ($this->replace)($input);

    expect($response->error()->code)->toBe($code)
        ->and($response->error()->kind)->toBe($kind)
        ->and($this->members->businessLookups)->toBe([])
        ->and($this->photos->replacements)->toBe([]);
})->with([
    'a member that is not a uuid' => [fn () => teamMemberPhotoUpload(staffMemberId: '42'), 'staff_member_not_found', DomainFailureKind::NotFound],
    'a gif' => [fn () => teamMemberPhotoUpload(mimeType: 'image/gif'), 'unsupported_profile_photo', DomainFailureKind::Invalid],
    'an empty file' => [fn () => teamMemberPhotoUpload(sizeInBytes: 0), 'unsupported_profile_photo', DomainFailureKind::Invalid],
    'a file past the limit' => [fn () => teamMemberPhotoUpload(sizeInBytes: 2 * 1024 * 1024 + 1), 'profile_photo_too_large', DomainFailureKind::Invalid],
]);

it('refuses a member nobody has without touching any photo', function () {
    $response = ($this->replace)(teamMemberPhotoUpload(staffMemberId: StaffFixtures::MEMBER_ID));

    expect($response->error()->code)->toBe('staff_member_not_found')
        ->and($this->photos->replacements)->toBe([]);
});

it('refuses a member with no profile without touching any photo', function () {
    $this->profiles = new FakeStaffProfileRepository;

    $response = ($this->replace)();

    expect($response->error()->code)->toBe('staff_profile_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->photos->replacements)->toBe([]);
});

it('answers the refusal of the photo store as a failure', function () {
    $this->photos = new FakeStaffProfilePhotos;

    expect(($this->replace)()->error()->code)->toBe('staff_profile_not_found');
});

it('lets a presenter refusal after the upload escape, rather than refusing a photo that was filed', function () {
    $this->accounts = new FakeAccountDirectory;

    expect(fn () => ($this->replace)())->toThrow(StaffMemberNotFound::class)
        ->and($this->photos->replacements)->toHaveCount(1)
        ->and($this->photos->replacements[0]['profileId'])->toBe(StaffFixtures::SECOND_PROFILE_ID);
});

describe('tenant isolation', function () {
    it('refuses a member of another business without touching any photo', function () {
        $response = ($this->replace)(teamMemberPhotoUpload(staffMemberId: StaffFixtures::THIRD_MEMBER_ID));

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->photos->replacements)->toBe([]);
    });

    it('files the photo under the business in context', function () {
        ($this->replace)(
            teamMemberPhotoUpload(staffMemberId: StaffFixtures::THIRD_MEMBER_ID),
            ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)),
        )->value();

        expect($this->photos->replacements[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and($this->photos->replacements[0]['profileId'])->toBe(StaffFixtures::THIRD_PROFILE_ID);
    });
});
