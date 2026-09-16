<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\LinkPlatformMismatch;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('names both halves it could not reconcile', function () {
    expect(LinkPlatformMismatch::between('instagram', 'facebook.com')->getMessage())
        ->toBe('Host [facebook.com] does not belong to the [instagram] platform.');
});

it('answers with a stable error code', function () {
    expect(LinkPlatformMismatch::between('instagram', 'facebook.com')->errorCode())
        ->toBe('link_platform_mismatch');
});

it('classifies a link filed under the wrong network as a 422, not a conflict', function () {
    expect(LinkPlatformMismatch::between('instagram', 'facebook.com')->kind())
        ->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(LinkPlatformMismatch::between('instagram', 'facebook.com'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][LinkPlatformMismatch::between('instagram', 'facebook.com')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
