<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidCoordinates;
use App\Domains\Addresses\ValueObjects\Coordinates;
use Tests\Support\Addresses\AddressFixtures;

it('holds the two halves of the point it was given', function () {
    $coordinates = Coordinates::of(AddressFixtures::LATITUDE, AddressFixtures::LONGITUDE);

    expect($coordinates->latitude)->toBe(AddressFixtures::LATITUDE)
        ->and($coordinates->longitude)->toBe(AddressFixtures::LONGITUDE);
});

it('has no way to carry half a point', function () {
    $parameters = (new ReflectionMethod(Coordinates::class, 'of'))->getParameters();

    expect((new ReflectionMethod(Coordinates::class, 'of'))->getNumberOfRequiredParameters())->toBe(2)
        ->and($parameters[0]->getType()?->allowsNull())->toBeFalse()
        ->and($parameters[1]->getType()?->allowsNull())->toBeFalse();
});

it('accepts the ends of the world it is allowed to name', function (float $latitude, float $longitude) {
    expect(Coordinates::of($latitude, $longitude))->toBeInstanceOf(Coordinates::class);
})->with([
    'the south pole' => [-90.0, 0.0],
    'the north pole' => [90.0, 0.0],
    'the far west of the antimeridian' => [0.0, -180.0],
    'the far east of the antimeridian' => [0.0, 180.0],
    'null island' => [0.0, 0.0],
    'both extremes at once' => [-90.0, -180.0],
]);

it('refuses a latitude no place on earth has', function (float $latitude) {
    expect(fn () => Coordinates::of($latitude, 0.0))->toThrow(InvalidCoordinates::class);
})->with([
    'a hair past the south pole' => -90.0000001,
    'a hair past the north pole' => 90.0000001,
    'a whole degree past the south pole' => -91.0,
    'a whole degree past the north pole' => 91.0,
    'a longitude mistaken for a latitude' => 180.0,
    'nonsense' => 1000.0,
    'negative infinity' => -INF,
    'infinity' => INF,
]);

it('refuses a longitude no place on earth has', function (float $longitude) {
    expect(fn () => Coordinates::of(0.0, $longitude))->toThrow(InvalidCoordinates::class);
})->with([
    'a hair past the antimeridian going west' => -180.0000001,
    'a hair past the antimeridian going east' => 180.0000001,
    'a whole degree past the antimeridian' => 181.0,
    'nonsense' => -1000.0,
    'negative infinity' => -INF,
    'infinity' => INF,
]);

it('quotes the value it refused, so a log says which half was wrong', function () {
    expect(fn () => Coordinates::of(91.0, 0.0))
        ->toThrow(InvalidCoordinates::class, 'The latitude [91] is outside the range a coordinate may take.')
        ->and(fn () => Coordinates::of(0.0, 181.0))
        ->toThrow(InvalidCoordinates::class, 'The longitude [181] is outside the range a coordinate may take.');
});

it('names the latitude first when both halves are out of range', function () {
    expect(fn () => Coordinates::of(91.0, 181.0))->toThrow(InvalidCoordinates::class, 'latitude');
});

it('keeps all seven decimals the column stores, without drifting', function () {
    $coordinates = Coordinates::of(19.4326077, -99.1332080);

    expect($coordinates->latitude)->toBe(19.4326077)
        ->and($coordinates->longitude)->toBe(-99.133208)
        ->and(json_encode(['latitude' => $coordinates->latitude]))->toBe('{"latitude":19.4326077}');
});

it('restores a stored point without asking the range again', function () {
    expect(Coordinates::restore(-999.0, 999.0)->latitude)->toBe(-999.0);
});

it('is equal to another point at the same place', function () {
    expect(Coordinates::of(19.4326077, -99.1332080)->equals(Coordinates::restore(19.4326077, -99.1332080)))
        ->toBeTrue();
});

it('is not equal to a point that differs in either half', function (float $latitude, float $longitude) {
    expect(Coordinates::of(19.4326077, -99.1332080)->equals(Coordinates::restore($latitude, $longitude)))
        ->toBeFalse();
})->with([
    'a different latitude' => [19.4326078, -99.1332080],
    'a different longitude' => [19.4326077, -99.1332081],
    'the halves swapped' => [-99.1332080, 19.4326077],
]);

it('refuses a point either half of which is not a number', function (float $latitude, float $longitude) {
    expect(fn () => Coordinates::of($latitude, $longitude))->toThrow(InvalidCoordinates::class);
})->with([
    'the latitude' => [NAN, 0.0],
    'the longitude' => [0.0, NAN],
    'both halves' => [NAN, NAN],
    'a latitude that is not a number beside an out of range longitude' => [NAN, 900.0],
]);

it('names both halves of a point that is not a number, and raises no warning doing it', function () {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        Coordinates::of(0.0, NAN);
        $thrown = null;
    } catch (InvalidCoordinates $escaped) {
        $thrown = $escaped;
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([])
        ->and($thrown?->getMessage())->toBe('The coordinate [latitude 0, longitude NAN] is not a finite number.');
});

it('refuses a point that is not finite before it asks whether it is in range', function () {
    expect(fn () => Coordinates::of(NAN, 900.0))
        ->toThrow(InvalidCoordinates::class, 'The coordinate [latitude NAN, longitude 900] is not a finite number.');
});

it('restores a stored point that is not a number without asking anything', function () {
    expect(is_nan(Coordinates::restore(NAN, 0.0)->latitude))->toBeTrue();
});
