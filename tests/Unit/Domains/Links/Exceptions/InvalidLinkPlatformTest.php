<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\InvalidLinkPlatform;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('quotes the platform it turned down', function () {
    expect(InvalidLinkPlatform::withValue('threads')->getMessage())
        ->toBe('[threads] is not a platform a link may point at.');
});

it('answers with a stable error code', function () {
    expect(InvalidLinkPlatform::withValue('threads')->errorCode())->toBe('invalid_link_platform');
});

it('classifies an unknown platform as a 422, not a conflict', function () {
    expect(InvalidLinkPlatform::withValue('threads')->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidLinkPlatform::withValue('threads'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidLinkPlatform::withValue('threads')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
