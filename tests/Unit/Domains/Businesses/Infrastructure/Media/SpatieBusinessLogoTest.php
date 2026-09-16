<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Infrastructure\Media\SpatieBusinessLogo;
use App\Shared\Infrastructure\Media\SafeFileName;

function businessLogoMethod(string $method): ReflectionMethod
{
    return new ReflectionMethod(SpatieBusinessLogo::class, $method);
}

describe('the port it stands behind', function () {
    it('is the adapter the business logo port describes', function () {
        expect(new SpatieBusinessLogo)->toBeInstanceOf(BusinessLogo::class);
    });

    it('is final, so nothing can subclass its way past the port', function () {
        expect((new ReflectionClass(SpatieBusinessLogo::class))->isFinal())->toBeTrue();
    });

    it('answers every method the port declares, with the port return type', function (string $method, string $returnType) {
        expect((string) businessLogoMethod($method)->getReturnType())->toBe($returnType)
            ->and((string) (new ReflectionMethod(BusinessLogo::class, $method))->getReturnType())->toBe($returnType);
    })->with([
        'reading the url' => ['urlFor', '?string'],
        'replacing the file' => ['replace', 'string'],
        'removing the file' => ['remove', 'void'],
    ]);

    it('exposes no method the port does not declare', function () {
        $public = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(SpatieBusinessLogo::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        expect($public)->toBe(['urlFor', 'replace', 'remove']);
    });
});

describe('keeping Illuminate\Http out of the application layer', function () {
    it('takes the upload as a path and a name, both plain strings', function () {
        $parameters = businessLogoMethod('replace')->getParameters();

        expect(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            $parameters,
        ))->toBe(['businessId:string', 'sourcePath:string', 'fileName:string']);
    });

    it('names no uploaded file in any signature it exposes', function () {
        $types = [];

        foreach ((new ReflectionClass(SpatieBusinessLogo::class))->getMethods() as $method) {
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
            (string) (new ReflectionClass(SpatieBusinessLogo::class))->getFileName(),
        );

        expect($source)->not->toContain('Illuminate\Http');
    });

    it('identifies the business it stores against by uuid, never an internal key', function (string $method) {
        $first = businessLogoMethod($method)->getParameters()[0];

        expect($first->getName())->toBe('businessId')
            ->and((string) $first->getType())->toBe('string');
    })->with(['urlFor', 'replace', 'remove']);
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper instead of carrying a copy of its own', function () {
        $reflection = new ReflectionClass(SpatieBusinessLogo::class);

        expect((string) file_get_contents((string) $reflection->getFileName()))
            ->toContain('SafeFileName::from($fileName, self::FALLBACK_FILE_NAME)')
            ->and($reflection->hasMethod('safeFileName'))->toBeFalse()
            ->and($reflection->hasMethod('slugged'))->toBeFalse();
    });

    it('hands the helper its own fallback name for an upload that carried none', function () {
        $fallbackName = (string) (new ReflectionClass(SpatieBusinessLogo::class))->getConstant('FALLBACK_FILE_NAME');

        expect($fallbackName)->toBe('logo')
            ->and(SafeFileName::from('***.png', $fallbackName))->toBe('logo.png');
    });

    it('names the helper on every method that accepts an upload', function () {
        $source = (string) file_get_contents(
            (string) (new ReflectionClass(SpatieBusinessLogo::class))->getFileName(),
        );

        expect(substr_count($source, 'SafeFileName::from('))->toBe(1);
    });
});
