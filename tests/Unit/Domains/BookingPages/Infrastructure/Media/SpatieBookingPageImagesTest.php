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

/**
 * @return list<string>
 */
function bookingPageImagesSignature(string $class, string $method): array
{
    return array_map(
        static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
        (new ReflectionMethod($class, $method))->getParameters(),
    );
}

function bookingPageImagesSource(?string $method = null): string
{
    $file = (string) file_get_contents((string) (new ReflectionClass(SpatieBookingPageImages::class))->getFileName());

    if ($method === null) {
        return $file;
    }

    $reflection = bookingPageImagesMethod($method);

    return implode("\n", array_slice(
        explode("\n", $file),
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    ));
}

dataset('every port method', [
    'reading the banner url' => 'bannerUrlFor',
    'reading the gallery' => 'galleryFor',
    'replacing the banner' => 'replaceBanner',
    'removing the banner' => 'removeBanner',
    'adding a gallery image' => 'addGalleryImage',
    'removing a gallery image' => 'removeGalleryImage',
    'reordering the gallery' => 'reorderGallery',
]);

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

    it('takes the same parameters the port declares, in the same order', function (string $method) {
        expect(bookingPageImagesSignature(SpatieBookingPageImages::class, $method))
            ->toBe(bookingPageImagesSignature(BookingPageImages::class, $method));
    })->with('every port method');

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

describe('the business every call is scoped to', function () {
    it('names the business before it names anything else', function (string $method) {
        $first = bookingPageImagesMethod($method)->getParameters()[0];

        expect($first->getName())->toBe('businessId')
            ->and((string) $first->getType())->toBe('string');
    })->with('every port method');

    it('identifies the booking page by uuid, right after the business', function (string $method) {
        $second = bookingPageImagesMethod($method)->getParameters()[1];

        expect($second->getName())->toBe('bookingPageId')
            ->and((string) $second->getType())->toBe('string');
    })->with('every port method');

    it('identifies a gallery image by uuid too, and only once the page is settled', function () {
        expect(bookingPageImagesSignature(SpatieBookingPageImages::class, 'removeGalleryImage'))
            ->toBe(['businessId:string', 'bookingPageId:string', 'imageId:string']);
    });

    it('takes the upload as a path and a name, both plain strings, after the page', function (string $method) {
        expect(bookingPageImagesSignature(SpatieBookingPageImages::class, $method))
            ->toBe(['businessId:string', 'bookingPageId:string', 'sourcePath:string', 'fileName:string']);
    })->with(['replaceBanner', 'addGalleryImage']);

    it('resolves the page through one business scoped lookup the whole class shares', function () {
        expect(bookingPageImagesSignature(SpatieBookingPageImages::class, 'ofBusiness'))
            ->toBe(['businessId:string'])
            ->and(bookingPageImagesSignature(SpatieBookingPageImages::class, 'modelOrFail'))
            ->toBe(['businessId:string', 'bookingPageId:string'])
            ->and(bookingPageImagesSignature(SpatieBookingPageImages::class, 'galleryImageOrFail'))
            ->toBe(['businessId:string', 'bookingPageId:string', 'imageId:string']);
    });

    it('narrows the query to the business with a subquery rather than a lookup of its own', function () {
        $source = bookingPageImagesSource('ofBusiness');

        expect($source)->toContain('whereIn(')
            ->and($source)->toContain('->from(self::BUSINESSES_TABLE)')
            ->and($source)->toContain("->where('uuid', \$businessId)")
            ->and($source)->not->toContain('->first()')
            ->and($source)->not->toContain('->value(');
    });

    it('reads the gallery without a second round trip for the business it belongs to', function () {
        $source = bookingPageImagesSource('galleryFor');

        expect($source)->toContain("self::ofBusiness(\$businessId)->with('media')->where('uuid', \$bookingPageId)->first()")
            ->and(substr_count($source, '->first()'))->toBe(1)
            ->and($source)->not->toContain('->get(');
    });

    it('reads the banner url the same way, one lookup narrowed by the business', function () {
        $source = bookingPageImagesSource('bannerUrlFor');

        expect($source)->toContain("self::ofBusiness(\$businessId)->with('media')->where('uuid', \$bookingPageId)->first()")
            ->and(substr_count($source, '->first()'))->toBe(1);
    });

    it('resolves the image a client named inside the page it already narrowed', function () {
        expect(bookingPageImagesSource('galleryImageOrFail'))
            ->toContain('$this->modelOrFail($businessId, $bookingPageId)')
            ->and(bookingPageImagesSource('removeGalleryImage'))
            ->toContain('$this->galleryImageOrFail($businessId, $bookingPageId, $imageId)');
    });
});

describe('the page it refuses to find', function () {
    it('names the business in the refusal, which is what the failure reads back', function () {
        expect(bookingPageImagesSource('modelOrFail'))
            ->toContain('throw BookingPageNotFound::forBusiness($businessId);')
            ->and(bookingPageImagesSource('modelOrFail'))
            ->not->toContain('BookingPageNotFound::forBusiness($bookingPageId)');
    });

    it('builds that refusal in one place, so a neighbour page and a missing one read alike', function () {
        expect(substr_count(bookingPageImagesSource(), 'BookingPageNotFound::'))->toBe(1);
    });

    it('answers a read about a page of another business with nothing, rather than refusing', function () {
        expect(bookingPageImagesSource('bannerUrlFor'))->toContain('return null;')
            ->and(bookingPageImagesSource('galleryFor'))->toContain('return [];')
            ->and(bookingPageImagesSource('bannerUrlFor'))->not->toContain('throw ')
            ->and(bookingPageImagesSource('galleryFor'))->not->toContain('throw ');
    });
});

describe('keeping Illuminate\Http out of the application layer', function () {
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
        expect(bookingPageImagesSource())->not->toContain('Illuminate\Http');
    });
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper instead of carrying a copy of its own', function () {
        $reflection = new ReflectionClass(SpatieBookingPageImages::class);

        expect(bookingPageImagesSource())
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
        expect(substr_count(bookingPageImagesSource(), 'SafeFileName::from('))->toBe(2);
    });
});
