<?php

declare(strict_types=1);

use App\Domains\Services\Infrastructure\Http\Requests\ListServicesRequest;
use App\Shared\ValueObjects\Pagination;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * @param  array<string, mixed>  $query
 * @return array<string, array<string, mixed>>
 */
function listServicesFailures(array $query): array
{
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))->make($query, (new ListServicesRequest)->rules());
    $validator->passes();

    return $validator->failed();
}

it('lets through the deepest page it serves', function () {
    expect(listServicesFailures(['page' => Pagination::MAXIMUM_PAGE]))->toBe([]);
});

it('refuses a page past the deepest one it serves', function (int $page) {
    expect(array_keys(listServicesFailures(['page' => $page])['page'] ?? []))->toBe(['Max']);
})->with([
    'one past the deepest page' => Pagination::MAXIMUM_PAGE + 1,
    'the largest integer' => PHP_INT_MAX,
]);
