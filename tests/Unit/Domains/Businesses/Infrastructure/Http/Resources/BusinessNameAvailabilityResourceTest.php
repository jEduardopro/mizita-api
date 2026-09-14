<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessNameAvailabilityResource;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedAvailability(NameAvailability $availability): array
{
    return (array) BusinessNameAvailabilityResource::make($availability)->response()->getData(true)['data'];
}

it('reports a free name with its slug and a null reason', function () {
    expect(serializedAvailability(NameAvailability::available('ada-salon')))->toBe([
        'available' => true,
        'slug' => 'ada-salon',
        'reason' => null,
    ]);
});

it('keeps the reason key present when the name is free', function () {
    $payload = BusinessNameAvailabilityResource::make(NameAvailability::available('ada-salon'))
        ->response()
        ->getData(true)['data'];

    expect($payload)->toHaveKey('reason')
        ->and($payload['reason'])->toBeNull();
});

it('reports a taken name with no slug and the taken reason', function () {
    expect(serializedAvailability(NameAvailability::taken()))->toBe([
        'available' => false,
        'slug' => null,
        'reason' => 'taken',
    ]);
});

it('reports a name that cannot be slugged with the not_sluggable reason', function () {
    expect(serializedAvailability(NameAvailability::notSluggable()))->toBe([
        'available' => false,
        'slug' => null,
        'reason' => 'not_sluggable',
    ]);
});

it('puts the backed value of the reason on the wire, never the enum case', function (NameAvailability $availability, string $reason) {
    expect(serializedAvailability($availability)['reason'])->toBe($reason)->toBeString();
})->with([
    'taken' => [fn () => NameAvailability::taken(), 'taken'],
    'not sluggable' => [fn () => NameAvailability::notSluggable(), 'not_sluggable'],
]);

it('wraps the payload in the data envelope', function () {
    expect(BusinessNameAvailabilityResource::make(NameAvailability::available('ada-salon'))->response()->getData(true))
        ->toBe(['data' => ['available' => true, 'slug' => 'ada-salon', 'reason' => null]]);
});

it('never serializes business_id', function () {
    expect(serializedAvailability(NameAvailability::available('ada-salon')))->not->toHaveKey('business_id');
});
