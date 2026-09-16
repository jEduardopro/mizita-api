<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\DuplicateLinkPlatform;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('names the platform that appeared twice', function () {
    expect(DuplicateLinkPlatform::for('instagram')->getMessage())
        ->toBe('Platform [instagram] appears more than once for the same owner.');
});

it('answers with a stable error code', function () {
    expect(DuplicateLinkPlatform::for('instagram')->errorCode())->toBe('duplicate_link_platform');
});

it('classifies a repeated platform as a conflict, because the partial unique index says so', function () {
    expect(DuplicateLinkPlatform::for('instagram')->kind())->toBe(DomainFailureKind::Conflict);
});

it('carries the interface the renderer is registered against', function () {
    expect(DuplicateLinkPlatform::for('instagram'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][DuplicateLinkPlatform::for('instagram')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
