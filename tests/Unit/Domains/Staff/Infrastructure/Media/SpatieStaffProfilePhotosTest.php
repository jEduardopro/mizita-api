<?php

declare(strict_types=1);

use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Infrastructure\Media\SpatieStaffProfilePhotos;
use App\Shared\Infrastructure\Media\SafeFileName;
use Tests\Support\Shared\ImageFiles;

function staffProfilePhotosSource(): string
{
    return (string) file_get_contents((string) (new ReflectionClass(SpatieStaffProfilePhotos::class))->getFileName());
}

it('is the adapter the staff profile photos port describes', function () {
    expect(new SpatieStaffProfilePhotos)->toBeInstanceOf(StaffProfilePhotos::class);
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper, handing it the stored file to sniff', function () {
        expect(staffProfilePhotosSource())
            ->toContain('SafeFileName::from($sourcePath, $fileName, self::FALLBACK_FILE_NAME)')
            ->and(substr_count(staffProfilePhotosSource(), 'SafeFileName::from('))->toBe(1);
    });

    it('hands the helper its own fallback name for an upload that carried none', function () {
        $fallbackName = (string) (new ReflectionClass(SpatieStaffProfilePhotos::class))->getConstant('FALLBACK_FILE_NAME');

        expect($fallbackName)->toBe('photo')
            ->and(SafeFileName::from(ImageFiles::jpeg(), '***.png', $fallbackName))->toBe('photo.jpg');
    });
});
