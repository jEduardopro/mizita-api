<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Application\Dtos\ReplaceMyProfilePhotoInput;
use App\Domains\Staff\Application\Presenters\MyProfilePresenter;
use App\Domains\Staff\Application\UseCases\ReplaceMyProfilePhoto;
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

const REPLACED_PHOTO_SOURCE = '/tmp/php-upload-ada';
const REPLACED_PHOTO_FILE = 'ada.webp';

function replacementPhoto(
    string $accountId = StaffFixtures::ACCOUNT_ID,
    string $mimeType = 'image/webp',
    int $sizeInBytes = 150_000,
    string $sourcePath = REPLACED_PHOTO_SOURCE,
): ReplaceMyProfilePhotoInput {
    return new ReplaceMyProfilePhotoInput($accountId, $sourcePath, REPLACED_PHOTO_FILE, $mimeType, $sizeInBytes);
}

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(StaffFixtures::member());
    $this->profiles = (new FakeStaffProfileRepository)->store(StaffFixtures::profile());
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account());
    $this->phones = new FakeStaffPhoneBook;
    $this->photos = (new FakeStaffProfilePhotos)->knows(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID);

    $this->build = fn (?FakeBusinessContext $business = null): ReplaceMyProfilePhoto => new ReplaceMyProfilePhoto(
        $this->members,
        $this->profiles,
        $this->photos,
        new MyProfilePresenter($this->accounts, $this->phones, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->replace = fn (?ReplaceMyProfilePhotoInput $input = null, ?ReplaceMyProfilePhoto $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle($input ?? replacementPhoto());

    $this->refusal = function (?ReplaceMyProfilePhotoInput $input = null): UseCaseError {
        $response = ($this->replace)($input);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('replacing the photo', function () {
    it('stores the upload against the profile under the business in context', function () {
        ($this->replace)();

        expect($this->photos->replacements)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'profileId' => StaffFixtures::PROFILE_ID,
            'sourcePath' => REPLACED_PHOTO_SOURCE,
            'fileName' => REPLACED_PHOTO_FILE,
        ]]);
    });

    it('answers with the whole profile carrying the new photo', function () {
        $this->phones->store(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        $data = ($this->replace)()->value();

        expect($data)->toBeInstanceOf(MyProfileData::class)
            ->and($data->id)->toBe(StaffFixtures::PROFILE_ID)
            ->and($data->staffMemberId)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->photoUrl)->toBe(FakeStaffProfilePhotos::urlOf(StaffFixtures::PROFILE_ID, REPLACED_PHOTO_FILE))
            ->and($data->photoUrl)->not->toBe(StaffFixtures::PHOTO_URL)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($data->about)->toBe(StaffFixtures::ABOUT)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('writes nothing but the photo', function () {
        ($this->replace)();

        expect($this->profiles->saved)->toBe([])
            ->and($this->accounts->renames)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    });
});

describe('refusing the upload', function () {
    it('refuses what the input rules refuse, storing nothing', function (ReplaceMyProfilePhotoInput $input, string $code) {
        $error = ($this->refusal)($input);

        expect($error->code)->toBe($code)
            ->and($this->photos->replacements)->toBe([])
            ->and($this->members->accountLookups)->toBe([]);
    })->with([
        'an account that is not a uuid' => [fn () => replacementPhoto(accountId: 'not-a-uuid'), 'staff_member_not_found'],
        'no file' => [fn () => replacementPhoto(sourcePath: ''), 'unsupported_profile_photo'],
        'a gif' => [fn () => replacementPhoto(mimeType: 'image/gif'), 'unsupported_profile_photo'],
        'an empty file' => [fn () => replacementPhoto(sizeInBytes: 0), 'unsupported_profile_photo'],
        'a photo past the size limit' => [fn () => replacementPhoto(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1), 'profile_photo_too_large'],
    ]);

    it('classifies every upload refusal as a correction the caller can make', function (ReplaceMyProfilePhotoInput $input) {
        expect(($this->refusal)($input)->kind)->toBe(DomainFailureKind::Invalid);
    })->with([
        'a gif' => [fn () => replacementPhoto(mimeType: 'image/gif')],
        'a photo past the size limit' => [fn () => replacementPhoto(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1)],
    ]);

    it('refuses a member with no profile, storing nothing', function () {
        $this->profiles = new FakeStaffProfileRepository;

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->replacements)->toBe([]);
    });

    it('answers with not found when the photo store has no such profile', function () {
        $this->photos = new FakeStaffProfilePhotos;

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound);
    });
});

describe('tenant isolation', function () {
    it('refuses an account whose only membership is at another business, storing nothing', function () {
        $this->members = (new FakeStaffMemberRepository)->store(StaffFixtures::member(businessId: StaffFixtures::OTHER_BUSINESS_ID));

        $error = ($this->refusal)();

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->replacements)->toBe([]);
    });

    it('stores the photo under the business in context when the account works at two', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID));
        $this->profiles->store(StaffFixtures::profile(
            id: StaffFixtures::SECOND_PROFILE_ID,
            staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));
        $this->photos->knows(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID);

        ($this->replace)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)));

        expect($this->photos->replacements)->toHaveCount(1)
            ->and($this->photos->replacements[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and($this->photos->replacements[0]['profileId'])->toBe(StaffFixtures::SECOND_PROFILE_ID);
    });
});
