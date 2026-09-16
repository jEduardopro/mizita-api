<?php

declare(strict_types=1);

use App\Http\Exceptions\PermissionDenied;
use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Responses\FailurePayload;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    app()->setLocale('en');
});

/**
 * @return array<string, mixed>
 */
function permissionDeniedOnTheWire(): array
{
    $response = (new RenderDomainFailure)(
        PermissionDenied::forCurrentBusiness(),
        Request::create('/api/services', 'POST'),
    );

    return json_decode($response->getContent(), associative: true);
}

it('names itself missing_permission', function () {
    expect(PermissionDenied::forCurrentBusiness()->errorCode())->toBe('missing_permission');
});

it('is forbidden, so the api answers 403 rather than 401 or 422', function () {
    $failure = PermissionDenied::forCurrentBusiness();

    expect($failure->kind())->toBe(DomainFailureKind::Forbidden)
        ->and(FailurePayload::for($failure->errorCode(), $failure->kind())->status)->toBe(403);
});

it('renders the flat message and code body the client already knows how to show', function () {
    expect(permissionDeniedOnTheWire())->toBe([
        'message' => 'You are not allowed to perform this action in this business.',
        'code' => 'missing_permission',
    ]);
});

it('names no permission, because what is missing is the owner to know and not the caller', function (string $locale) {
    app()->setLocale($locale);

    $body = json_encode(permissionDeniedOnTheWire());

    expect($body)->not->toContain('create_service')
        ->and($body)->not->toContain('view_services')
        ->and($body)->not->toContain('permission:');
})->with(['en', 'es']);

it('names no permission in the developer message either, since the middleware knows which one', function () {
    expect(PermissionDenied::forCurrentBusiness()->getMessage())
        ->toBe('The caller is not allowed to perform this action in this business.');
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    app()->setLocale($locale);

    expect(FailurePayload::messageFor('missing_permission'))
        ->toBeString()->not->toBe('')->not->toStartWith('messages.errors.');
})->with(['en', 'es']);

it('is a throwable domain failure, so the handler can both catch and classify it', function () {
    expect(PermissionDenied::forCurrentBusiness())->toBeInstanceOf(DomainFailure::class)
        ->and(PermissionDenied::forCurrentBusiness())->toBeInstanceOf(Throwable::class);
});

it('lives in the http kernel and not in a domain, because the middleware is not a domain', function () {
    $file = (new ReflectionClass(PermissionDenied::class))->getFileName();

    expect($file)->toContain('/app/Http/Exceptions/')
        ->and($file)->not->toContain('/app/Domains/');
});
