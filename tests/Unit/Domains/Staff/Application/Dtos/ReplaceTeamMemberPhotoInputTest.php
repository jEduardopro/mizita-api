<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ReplaceMyProfilePhotoInput;
use App\Domains\Staff\Application\Dtos\ReplaceTeamMemberPhotoInput;
use App\Domains\Staff\Exceptions\ProfilePhotoTooLarge;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\UnsupportedProfilePhoto;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Staff\StaffFixtures;

function teamMemberPhoto(
    string $staffMemberId = StaffFixtures::MEMBER_ID,
    string $sourcePath = '/tmp/php-upload-1',
    string $mimeType = 'image/webp',
    int $sizeInBytes = 1024,
): ReplaceTeamMemberPhotoInput {
    return new ReplaceTeamMemberPhotoInput($staffMemberId, $sourcePath, 'ada.webp', $mimeType, $sizeInBytes);
}

function teamMemberPhotoRefusal(ReplaceTeamMemberPhotoInput $input): ?Throwable
{
    try {
        $input->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

it('returns silently for every photo type the profile can show', function (string $mimeType) {
    expect(teamMemberPhotoRefusal(teamMemberPhoto(mimeType: $mimeType)))->toBeNull();
})->with(ReplaceMyProfilePhotoInput::ACCEPTED_MIME_TYPES);

it('takes a photo of exactly the maximum size', function () {
    expect(teamMemberPhotoRefusal(teamMemberPhoto(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES)))->toBeNull();
});

it('refuses the upload with the failure its first broken rule names', function (ReplaceTeamMemberPhotoInput $input, string $exception) {
    $refusal = teamMemberPhotoRefusal($input);

    expect($refusal)->toBeInstanceOf($exception)
        ->and($refusal)->toBeInstanceOf(DomainFailure::class);
})->with([
    'a member that is not a uuid' => [fn () => teamMemberPhoto(staffMemberId: '42'), StaffMemberNotFound::class],
    'no uploaded file' => [fn () => teamMemberPhoto(sourcePath: '  '), UnsupportedProfilePhoto::class],
    'a gif' => [fn () => teamMemberPhoto(mimeType: 'image/gif'), UnsupportedProfilePhoto::class],
    'an empty mime type' => [fn () => teamMemberPhoto(mimeType: ''), UnsupportedProfilePhoto::class],
    'an uppercased mime type' => [fn () => teamMemberPhoto(mimeType: 'IMAGE/PNG'), UnsupportedProfilePhoto::class],
    'an empty file' => [fn () => teamMemberPhoto(sizeInBytes: 0), UnsupportedProfilePhoto::class],
    'a negative size' => [fn () => teamMemberPhoto(sizeInBytes: -1), UnsupportedProfilePhoto::class],
    'one byte past the limit' => [fn () => teamMemberPhoto(sizeInBytes: ReplaceMyProfilePhotoInput::MAXIMUM_BYTES + 1), ProfilePhotoTooLarge::class],
    'a bad member before a bad file' => [fn () => teamMemberPhoto(staffMemberId: '', sizeInBytes: 0), StaffMemberNotFound::class],
]);
