<?php

declare(strict_types=1);

use App\Domains\Notifications\Application\Dtos\CountUnreadStaffNotificationsInput;
use App\Domains\Notifications\Infrastructure\Http\Requests\CountUnreadStaffNotificationsRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

/**
 * @param  array<string, mixed>  $query
 * @return array<string, array<string, mixed>>
 */
function countUnreadStaffNotificationsFailures(array $query): array
{
    $validator = (new Factory(new Translator(new ArrayLoader, 'en')))
        ->make($query, (new CountUnreadStaffNotificationsRequest)->rules());
    $validator->passes();

    return $validator->failed();
}

it('lets through an empty query', function () {
    expect(countUnreadStaffNotificationsFailures([]))->toBe([]);
});

it('lets through every scope the domain serves, and only what the input it feeds accepts', function (string $scope) {
    expect(countUnreadStaffNotificationsFailures(['scope' => $scope]))->toBe([])
        ->and(fn () => CountUnreadStaffNotificationsInput::fromRequest(['scope' => $scope], NotificationsFixtures::MEMBER_ACCOUNT_ID)->validate())
        ->not->toThrow(Throwable::class);
})->with(['mine', 'team']);

it('refuses a scope the domain would not serve', function (mixed $scope) {
    expect(countUnreadStaffNotificationsFailures(['scope' => $scope]))->toHaveKey('scope');
})->with([
    'an unknown scope' => 'everyone',
    'a scope in upper case' => 'TEAM',
    'a list' => [['team']],
]);
