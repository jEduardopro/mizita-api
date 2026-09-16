<?php

declare(strict_types=1);

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Infrastructure\Media\SpatieBookingPageImages;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use App\Shared\Infrastructure\Media\SafeFileName;
use Tests\Support\FakeTransactionManager;

function bookingPageImagesMethod(string $method): ReflectionMethod
{
    return new ReflectionMethod(SpatieBookingPageImages::class, $method);
}

describe('the port it stands behind', function () {
    it('is the adapter the booking page images port describes', function () {
        expect(new SpatieBookingPageImages(new FakeTransactionManager))->toBeInstanceOf(BookingPageImages::class);
    });

    it('is final, so nothing can subclass its way past the port', function () {
        expect((new ReflectionClass(SpatieBookingPageImages::class))->isFinal())->toBeTrue();
    });

    it('answers every method the port declares, with the port return type', function (string $method, string $returnType) {
        expect((string) bookingPageImagesMethod($method)->getReturnType())->toBe($returnType)
            ->and((string) (new ReflectionMethod(BookingPageImages::class, $method))->getReturnType())->toBe($returnType);
    })->with([
        'reading the banner url' => ['bannerUrlFor', '?string'],
        'reading the gallery' => ['galleryFor', 'array'],
        'replacing the banner' => ['replaceBanner', 'string'],
        'removing the banner' => ['removeBanner', 'void'],
        'adding a gallery image' => ['addGalleryImage', BookingPageImage::class],
        'removing a gallery image' => ['removeGalleryImage', 'void'],
        'reordering the gallery' => ['reorderGallery', 'void'],
    ]);

    it('exposes no method the port does not declare', function () {
        $public = array_values(array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            array_filter(
                (new ReflectionClass(SpatieBookingPageImages::class))->getMethods(ReflectionMethod::IS_PUBLIC),
                static fn (ReflectionMethod $method): bool => ! $method->isConstructor(),
            ),
        ));

        expect($public)->toBe([
            'bannerUrlFor',
            'galleryFor',
            'replaceBanner',
            'removeBanner',
            'addGalleryImage',
            'removeGalleryImage',
            'reorderGallery',
        ]);
    });

    it('takes the transaction manager as a port, so reordering is testable without a database', function () {
        $constructor = (new ReflectionClass(SpatieBookingPageImages::class))->getConstructor();

        expect(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            $constructor?->getParameters() ?? [],
        ))->toBe(['transactions:App\Shared\Contracts\TransactionManager']);
    });
});

describe('keeping Illuminate\Http out of the application layer', function () {
    it('takes the upload as a path and a name, both plain strings', function (string $method) {
        expect(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            bookingPageImagesMethod($method)->getParameters(),
        ))->toBe(['bookingPageId:string', 'sourcePath:string', 'fileName:string']);
    })->with(['replaceBanner', 'addGalleryImage']);

    it('names no uploaded file in any signature it exposes', function () {
        $types = [];

        foreach ((new ReflectionClass(SpatieBookingPageImages::class))->getMethods() as $method) {
            $types[] = (string) $method->getReturnType();

            foreach ($method->getParameters() as $parameter) {
                $types[] = (string) $parameter->getType();
            }
        }

        expect($types)->not->toContain('Illuminate\Http\UploadedFile')
            ->and($types)->not->toContain('?Illuminate\Http\UploadedFile');
    });

    it('imports nothing from Illuminate\Http, which is what the port is for', function () {
        $source = (string) file_get_contents(
            (string) (new ReflectionClass(SpatieBookingPageImages::class))->getFileName(),
        );

        expect($source)->not->toContain('Illuminate\Http');
    });

    it('identifies the booking page it stores against by uuid, never an internal key', function (string $method) {
        $first = bookingPageImagesMethod($method)->getParameters()[0];

        expect($first->getName())->toBe('bookingPageId')
            ->and((string) $first->getType())->toBe('string');
    })->with([
        'bannerUrlFor',
        'galleryFor',
        'replaceBanner',
        'removeBanner',
        'addGalleryImage',
        'removeGalleryImage',
        'reorderGallery',
    ]);

    it('identifies a gallery image by uuid too, never an internal key', function () {
        $second = bookingPageImagesMethod('removeGalleryImage')->getParameters()[1];

        expect($second->getName())->toBe('imageId')
            ->and((string) $second->getType())->toBe('string');
    });
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper instead of carrying a copy of its own', function () {
        $reflection = new ReflectionClass(SpatieBookingPageImages::class);

        expect((string) file_get_contents((string) $reflection->getFileName()))
            ->toContain('SafeFileName::from($fileName, self::FALLBACK_FILE_NAME)')
            ->and($reflection->hasMethod('safeFileName'))->toBeFalse()
            ->and($reflection->hasMethod('slugged'))->toBeFalse();
    });

    it('hands the helper its own fallback name for an upload that carried none', function () {
        $fallbackName = (string) (new ReflectionClass(SpatieBookingPageImages::class))->getConstant('FALLBACK_FILE_NAME');

        expect($fallbackName)->toBe('image')
            ->and(SafeFileName::from('***.png', $fallbackName))->toBe('image.png');
    });

    it('names the helper on every method that accepts an upload', function () {
        $source = (string) file_get_contents(
            (string) (new ReflectionClass(SpatieBookingPageImages::class))->getFileName(),
        );

        expect(substr_count($source, 'SafeFileName::from('))->toBe(2);
    });
});
