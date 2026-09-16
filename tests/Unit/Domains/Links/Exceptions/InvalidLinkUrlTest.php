<?php

declare(strict_types=1);

use App\Domains\Links\Exceptions\InvalidLinkUrl;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells the four ways a url is unusable apart', function () {
    expect(InvalidLinkUrl::empty()->getMessage())->toBe('A link url cannot be empty.')
        ->and(InvalidLinkUrl::malformed()->getMessage())->toBe('The url offered is not a readable web address.')
        ->and(InvalidLinkUrl::unsupportedScheme()->getMessage())
        ->toBe('A link url must be served over http or https.')
        ->and(InvalidLinkUrl::tooLong()->getMessage())
        ->toBe('The url offered is longer than a link url may be.');
});

it('never quotes the url back, so a refused address stays out of the logs', function (InvalidLinkUrl $failure) {
    expect($failure->getMessage())->not->toContain('http');
})->with([
    'empty' => fn () => InvalidLinkUrl::empty(),
    'malformed' => fn () => InvalidLinkUrl::malformed(),
    'too long' => fn () => InvalidLinkUrl::tooLong(),
]);

it('answers with one error code however the url fell short', function (InvalidLinkUrl $failure) {
    expect($failure->errorCode())->toBe('invalid_link_url');
})->with([
    'empty' => fn () => InvalidLinkUrl::empty(),
    'malformed' => fn () => InvalidLinkUrl::malformed(),
    'unsupported scheme' => fn () => InvalidLinkUrl::unsupportedScheme(),
    'too long' => fn () => InvalidLinkUrl::tooLong(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidLinkUrl $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidLinkUrl::empty(),
    'malformed' => fn () => InvalidLinkUrl::malformed(),
    'unsupported scheme' => fn () => InvalidLinkUrl::unsupportedScheme(),
    'too long' => fn () => InvalidLinkUrl::tooLong(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidLinkUrl::empty())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidLinkUrl::empty()->errorCode()] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);
