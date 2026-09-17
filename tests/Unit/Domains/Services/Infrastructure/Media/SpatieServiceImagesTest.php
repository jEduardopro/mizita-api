<?php

declare(strict_types=1);

use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Infrastructure\Media\SpatieServiceImages;
use App\Shared\Infrastructure\Media\SafeFileName;

function serviceImagesMethod(string $method): ReflectionMethod
{
    return new ReflectionMethod(SpatieServiceImages::class, $method);
}

function serviceImagesSource(): string
{
    return (string) file_get_contents(
        (string) (new ReflectionClass(SpatieServiceImages::class))->getFileName(),
    );
}

function serviceImagesBody(string $method): string
{
    $reflection = serviceImagesMethod($method);
    $lines = (array) file((string) $reflection->getFileName());

    return implode('', array_slice(
        $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    ));
}

function serviceImagesSignature(string $method): array
{
    return array_map(
        static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
        serviceImagesMethod($method)->getParameters(),
    );
}

describe('the port it stands behind', function () {
    it('is the adapter the service images port describes', function () {
        expect(new SpatieServiceImages)->toBeInstanceOf(ServiceImages::class);
    });

    it('is final, so nothing can subclass its way past the port', function () {
        expect((new ReflectionClass(SpatieServiceImages::class))->isFinal())->toBeTrue();
    });

    it('answers every method the port declares, with the port return type', function (string $method, string $returnType) {
        expect((string) serviceImagesMethod($method)->getReturnType())->toBe($returnType)
            ->and((string) (new ReflectionMethod(ServiceImages::class, $method))->getReturnType())->toBe($returnType);
    })->with([
        'reading one url' => ['urlFor', '?string'],
        'reading many urls' => ['urlsFor', 'array'],
        'replacing the file' => ['replace', 'string'],
        'removing the file' => ['remove', 'void'],
        'copying the file onto another service' => ['copy', 'void'],
    ]);

    it('exposes no method the port does not declare', function () {
        $public = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(SpatieServiceImages::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        expect($public)->toBe(['urlFor', 'urlsFor', 'replace', 'remove', 'copy']);
    });

    it('signs every method the way the port signs it', function (string $method) {
        expect(serviceImagesSignature($method))->toBe(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            (new ReflectionMethod(ServiceImages::class, $method))->getParameters(),
        ));
    })->with(['urlFor', 'urlsFor', 'replace', 'remove', 'copy']);
});

describe('scoping every file to the business that owns the service', function () {
    it('takes the business before anything else, on every method the port declares', function (string $method) {
        $first = serviceImagesMethod($method)->getParameters()[0];

        expect($first->getName())->toBe('businessId')
            ->and((string) $first->getType())->toBe('string');
    })->with(['urlFor', 'urlsFor', 'replace', 'remove', 'copy']);

    it('names the service after the business, never in its place', function (string $method) {
        $second = serviceImagesMethod($method)->getParameters()[1];

        expect($second->getName())->toBe('serviceId')
            ->and((string) $second->getType())->toBe('string');
    })->with(['urlFor', 'replace', 'remove']);

    it('reaches the services table through one business-scoped query and nowhere else', function () {
        expect(substr_count(serviceImagesSource(), 'ServiceModel::query()'))->toBe(1)
            ->and(serviceImagesBody('ofBusiness'))->toContain('ServiceModel::query()')
            ->and(serviceImagesBody('ofBusiness'))->toContain("->where('uuid', \$businessId)");
    });

    it('runs every lookup through that scope, with the business it was handed', function (string $method) {
        expect(serviceImagesBody($method))->toContain('self::ofBusiness($businessId)');
    })->with(['urlFor', 'urlsFor', 'modelOrFail']);

    it('goes through the guarded lookup before it writes anything', function (string $method) {
        expect(serviceImagesBody($method))->toContain('$this->modelOrFail($businessId,');
    })->with(['replace', 'remove', 'copy']);
});

describe('copying a file from one service onto another', function () {
    it('takes one business and two services, so a cross-business copy cannot be expressed', function () {
        expect(serviceImagesSignature('copy'))
            ->toBe(['businessId:string', 'sourceServiceId:string', 'targetServiceId:string']);
    });

    it('resolves both ends under that one business', function () {
        $body = serviceImagesBody('copy');

        expect($body)->toContain('$this->modelOrFail($businessId, $sourceServiceId)')
            ->and($body)->toContain('$this->modelOrFail($businessId, $targetServiceId)')
            ->and(substr_count($body, 'modelOrFail($businessId'))->toBe(2);
    });
});

describe('what a caller learns about a service that is not theirs', function () {
    it('answers a read with null, which is what a service with no image answers too', function () {
        $body = serviceImagesBody('urlFor');

        expect($body)->toContain('return null;')
            ->and($body)->not->toContain('throw')
            ->and((string) serviceImagesMethod('urlFor')->getReturnType())->toBe('?string');
    });

    it('refuses a write as not found, never as forbidden, because a 403 would confirm the row exists', function () {
        $source = serviceImagesSource();

        expect(serviceImagesBody('modelOrFail'))->toContain('throw ServiceNotFound::withId($serviceId);')
            ->and($source)->not->toContain('Forbidden')
            ->and($source)->not->toContain('abort(')
            ->and($source)->not->toContain('403');
    });

    it('names only the service in that refusal, never the business that holds it', function () {
        expect(serviceImagesBody('modelOrFail'))->not->toContain('withId($businessId');
    });
});

describe('reading the images of a whole page', function () {
    it('asks the database once for the page, never once per service', function () {
        $body = serviceImagesBody('urlsFor');

        expect(substr_count($body, '->get()'))->toBe(1)
            ->and(substr_count($body, 'ofBusiness'))->toBe(1)
            ->and($body)->toContain("whereIn('uuid', \$serviceIds)")
            ->and($body)->not->toContain('$this->urlFor(');
    });

    it('eager-loads the media, so reading each row costs no query of its own', function () {
        expect(serviceImagesBody('urlsFor'))->toContain("with('media')");
    });

    it('asks for nothing at all when the page holds no service', function () {
        expect(serviceImagesBody('urlsFor'))->toContain('if ($serviceIds === []) {');
    });

    it('promises a map of urls, so an id it found nothing for is absent rather than null', function () {
        expect((string) (new ReflectionMethod(ServiceImages::class, 'urlsFor'))->getDocComment())
            ->toContain('@return array<string, string>')
            ->and((string) serviceImagesMethod('urlsFor')->getDocComment())
            ->toContain('@return array<string, string>');
    });
});

describe('keeping Illuminate\Http out of the application layer', function () {
    it('takes the upload as a path and a name, both plain strings', function () {
        expect(serviceImagesSignature('replace'))
            ->toBe(['businessId:string', 'serviceId:string', 'sourcePath:string', 'fileName:string']);
    });

    it('names no uploaded file in any signature it exposes', function () {
        $types = [];

        foreach ((new ReflectionClass(SpatieServiceImages::class))->getMethods() as $method) {
            $types[] = (string) $method->getReturnType();

            foreach ($method->getParameters() as $parameter) {
                $types[] = (string) $parameter->getType();
            }
        }

        expect($types)->not->toContain('Illuminate\Http\UploadedFile')
            ->and($types)->not->toContain('?Illuminate\Http\UploadedFile');
    });

    it('imports nothing from Illuminate\Http, which is what the port is for', function () {
        expect(serviceImagesSource())->not->toContain('Illuminate\Http');
    });
});

describe('the file name it stores an upload under', function () {
    it('delegates the naming rule to the shared helper instead of carrying a copy of its own', function () {
        $reflection = new ReflectionClass(SpatieServiceImages::class);

        expect(serviceImagesSource())
            ->toContain('SafeFileName::from($fileName, self::FALLBACK_FILE_NAME)')
            ->and($reflection->hasMethod('safeFileName'))->toBeFalse()
            ->and($reflection->hasMethod('slugged'))->toBeFalse();
    });

    it('hands the helper its own fallback name for an upload that carried none', function () {
        $fallbackName = (string) (new ReflectionClass(SpatieServiceImages::class))->getConstant('FALLBACK_FILE_NAME');

        expect($fallbackName)->toBe('image')
            ->and(SafeFileName::from('***.png', $fallbackName))->toBe('image.png');
    });

    it('names the helper on every method that accepts an upload', function () {
        expect(substr_count(serviceImagesSource(), 'SafeFileName::from('))->toBe(1);
    });
});
