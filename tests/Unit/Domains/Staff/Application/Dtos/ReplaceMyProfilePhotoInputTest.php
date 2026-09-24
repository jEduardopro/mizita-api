<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ReplaceMyProfilePhotoInput;
use App\Domains\Staff\Exceptions\ProfilePhotoTooLarge;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\UnsupportedProfilePhoto;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Staff\StaffFixtures;

function profilePhotoUpload(
    string $accountId = StaffFixtures::ACCOUNT_ID,
    string $sourcePath = '/tmp/php-upload-ada',
    string $fileName = 'ada.webp',
    string $mimeType = 'image/webp',
    int $sizeInBytes = 150_000,
): ReplaceMyProfilePhotoInput {
    return new ReplaceMyProfilePhotoInput($accountId, $sourcePath, $fileName, $mimeType, $sizeInBytes);
}

it('returns silently for a supported photo', function (string $mimeType) {
    expect(fn () => profilePhotoUpload(mimeType: $mimeType)->validate())->not->toThrow(Throwable::class);
})->with(ReplaceMyProfilePhotoInput::ACCEPTED_MIME_TYPES);

it('accepts a photo of exactly the maximum size, and of a single byte', function (int $sizeInBytes) {
    expect(fn () => profilePhotoUpload(sizeInBytes: $sizeInBytes)->validate())->not->toThrow(Throwable::class);
})->with([
    'the maximum' => ReplaceMyProfilePhotoInput::MAXIMUM_BYTES,
    'one byte' => 1,
]);

it('refuses an upload the form request would have refused', function (ReplaceMyProfilePhotoInput $input, string $exception) {
    try {
        $input->validate();
        $thrown = null;
    } catch (Throwable $failure) {
        $thrown = $failure;
    }

    expect($thrown)->toBeInstanceOf($exception)
        ->and($thrown)->toBeInstanceOf(DomainFailure::class);
})->with([
    'an account that is not a uuid' => [fn () => profilePhotoUpload(accountId: 'not-a-uuid'), StaffMemberNotFound::class],
    'an empty account' => [fn () => profilePhotoUpload(accountId: ''), StaffMemberNotFound::class],
    'no file on disk' => [fn () => profilePhotoUpload(sourcePath: ''), UnsupportedProfilePhoto::class],
    'a whitespace-only path' => [fn () => profilePhotoUpload(sourcePath: '   '), UnsupportedProfilePhoto::class],
    'a gif' => [fn () => profilePhotoUpload(mimeType: 'image/gif'), UnsupportedProfilePhoto::class],
    'a pdf' => [fn () => profilePhotoUpload(mimeType: 'application/pdf'), UnsupportedProfilePhoto::class],
    'an svg' => [fn () => profilePhotoUpload(mimeType: 'image/svg+xml'), UnsupportedProfilePhoto::class],
    'no mime type' => [fn () => profilePhotoUpload(mimeType: ''), UnsupportedProfilePhoto::class],
    'an empty file' => [fn () => profilePhotoUpload(sizeInBytes: 0), UnsupportedProfilePhoto::class],
    'a negative size' => [fn () => profilePhotoUpload(sizeInBytes: -1), UnsupportedProfilePhoto::class],
    'one byte past the maximum' => [fn () => profilePhotoUpload(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1), ProfilePhotoTooLarge::class],
]);

it('says how large the photo was and how large it may be', function () {
    expect(fn () => profilePhotoUpload(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1)->validate())
        ->toThrow(ProfilePhotoTooLarge::class, 'A profile photo takes up to [2097152] bytes, got [2097153].');
});

it('checks the account first, then the file, then the type, then the size', function (ReplaceMyProfilePhotoInput $input, string $exception) {
    expect(fn () => $input->validate())->toThrow($exception);
})->with([
    'a bad account beats everything' => [fn () => profilePhotoUpload(accountId: 'nope', sourcePath: '', mimeType: 'image/gif', sizeInBytes: 0), StaffMemberNotFound::class],
    'a missing file beats a bad type' => [fn () => profilePhotoUpload(sourcePath: '', mimeType: 'image/gif'), UnsupportedProfilePhoto::class],
    'a bad type beats a bad size' => [fn () => profilePhotoUpload(mimeType: 'image/gif', sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1), UnsupportedProfilePhoto::class],
]);
