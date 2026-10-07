<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\ListStaffNotificationsInput;
use App\Domains\Notifications\Infrastructure\Http\Requests\ListStaffNotificationsRequest;
use App\Shared\ValueObjects\Pagination;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

/**
 * @param  array<string, mixed>  $query
 * @return array<string, array<string, mixed>>
 */
function listStaffNotificationsFailures(array $query): array
{
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))
        ->make($query, (new ListStaffNotificationsRequest)->rules());
    $validator->passes();

    return $validator->failed();
}

it('lets through an empty query', function () {
    expect(listStaffNotificationsFailures([]))->toBe([]);
});

it('lets through every scope, status and page the domain serves', function (array $query) {
    expect(listStaffNotificationsFailures($query))->toBe([]);
})->with([
    'my notifications' => [['scope' => 'mine']],
    'the team' => [['scope' => 'team']],
    'unread only' => [['status' => 'unread']],
    'all of them' => [['status' => 'all']],
    'the deepest page' => [['page' => Pagination::MAXIMUM_PAGE]],
    'the largest page size' => [['per_page' => Pagination::MAXIMUM_PER_PAGE]],
]);

it('refuses a query the domain would not serve', function (array $query, string $field) {
    expect(listStaffNotificationsFailures($query))->toHaveKey($field);
})->with([
    'an unknown scope' => [['scope' => 'everyone'], 'scope'],
    'an unknown status' => [['status' => 'archived'], 'status'],
    'page zero' => [['page' => 0], 'page'],
    'a page past the deepest' => [['page' => Pagination::MAXIMUM_PAGE + 1], 'page'],
    'a page that is no number' => [['page' => 'second'], 'page'],
    'a page size of zero' => [['per_page' => 0], 'per_page'],
    'a page size past the largest' => [['per_page' => Pagination::MAXIMUM_PER_PAGE + 1], 'per_page'],
]);

it('lets through only what the input it feeds accepts', function (array $query) {
    expect(listStaffNotificationsFailures($query))->toBe([])
        ->and(fn () => ListStaffNotificationsInput::fromRequest($query, NotificationsFixtures::MEMBER_ACCOUNT_ID)->validate())
        ->not->toThrow(Throwable::class);
})->with([
    'the team, unread, on a deep page' => [['scope' => 'team', 'status' => 'unread', 'page' => 40, 'per_page' => 25]],
    'my notifications on the first page' => [['scope' => 'mine', 'status' => 'all', 'page' => 1]],
]);
