<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\InvalidPlatformBusinessFilter;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{InvalidPlatformBusinessFilter}>
 */
function platformBusinessFilterFailures(): array
{
    return [
        'a search too long' => [InvalidPlatformBusinessFilter::searchTooLong(120)],
        'an unknown sort' => [InvalidPlatformBusinessFilter::unknownSort('plan')],
        'an unknown direction' => [InvalidPlatformBusinessFilter::unknownDirection('sideways')],
        'a page out of range' => [InvalidPlatformBusinessFilter::pageOutOfRange(0)],
        'a page size out of range' => [InvalidPlatformBusinessFilter::perPageOutOfRange(101, 100)],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (InvalidPlatformBusinessFilter $failure) {
    expect($failure->errorCode())->toBe('invalid_platform_business_filter');
})->with(platformBusinessFilterFailures());

it('classifies the refusal as invalid, which the edge renders as 422', function (InvalidPlatformBusinessFilter $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with(platformBusinessFilterFailures());

it('carries the interface the renderer is registered against', function (InvalidPlatformBusinessFilter $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(platformBusinessFilterFailures());

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['invalid_platform_business_filter'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('says what it turned down', function () {
    expect(InvalidPlatformBusinessFilter::searchTooLong(120)->getMessage())
        ->toBe('A business search may not run past 120 characters.')
        ->and(InvalidPlatformBusinessFilter::unknownSort('plan')->getMessage())
        ->toBe('The business list cannot be sorted by [plan].')
        ->and(InvalidPlatformBusinessFilter::unknownDirection('sideways')->getMessage())
        ->toBe('The sort direction [sideways] is neither ascending nor descending.')
        ->and(InvalidPlatformBusinessFilter::pageOutOfRange(0)->getMessage())
        ->toBe('The page [0] is not a page the business list can have.')
        ->and(InvalidPlatformBusinessFilter::perPageOutOfRange(101, 100)->getMessage())
        ->toBe('A business list page holds between 1 and 100 rows, got [101].');
});
