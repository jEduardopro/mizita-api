<?php

declare(strict_types=1);

use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Infrastructure\Media\SpatieCustomerPhotos;
use App\Shared\Infrastructure\Media\SafeFileName;
use Tests\Support\Shared\ImageFiles;

function customerPhotosSource(): string
{
    return (string) file_get_contents((string) (new ReflectionClass(SpatieCustomerPhotos::class))->getFileName());
}

it('is the adapter the customer photos port describes', function () {
    expect(new SpatieCustomerPhotos)->toBeInstanceOf(CustomerPhotos::class);
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper, handing it the stored file to sniff', function () {
        expect(customerPhotosSource())
            ->toContain('SafeFileName::from($sourcePath, $fileName, self::FALLBACK_FILE_NAME)')
            ->and(substr_count(customerPhotosSource(), 'SafeFileName::from('))->toBe(1);
    });

    it('hands the helper its own fallback name for an upload that carried none', function () {
        $fallbackName = (string) (new ReflectionClass(SpatieCustomerPhotos::class))->getConstant('FALLBACK_FILE_NAME');

        expect($fallbackName)->toBe('photo')
            ->and(SafeFileName::from(ImageFiles::webp(), '***.png', $fallbackName))->toBe('photo.webp');
    });
});
