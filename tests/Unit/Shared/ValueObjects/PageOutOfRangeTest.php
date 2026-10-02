<?php

declare(strict_types=1);

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\PageOutOfRange;

it('quotes the page it refused and the deepest one served', function () {
    expect(PageOutOfRange::forPage(0, 10000)->getMessage())
        ->toBe('A page has to fall between 1 and [10000], got [0].');
});

it('answers with a stable error code', function () {
    expect(PageOutOfRange::forPage(0, 10000)->errorCode())->toBe('page_out_of_range');
});

it('classifies the refusal as invalid, which the edge renders as 422', function () {
    expect(PageOutOfRange::forPage(0, 10000)->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(PageOutOfRange::forPage(0, 10000))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale, string $sentence) {
    $messages = require dirname(__DIR__, 4)."/lang/{$locale}/messages.php";

    expect($messages['errors'][PageOutOfRange::forPage(0, 10000)->errorCode()] ?? null)->toBe($sentence);
})->with([
    'english' => ['en', 'That page does not exist.'],
    'spanish' => ['es', 'Esa página no existe.'],
]);
