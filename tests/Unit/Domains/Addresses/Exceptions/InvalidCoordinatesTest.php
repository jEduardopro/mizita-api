<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidCoordinates;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says which half of the point was out of range', function () {
    expect(InvalidCoordinates::latitudeOutOfRange(91.5)->getMessage())
        ->toBe('The latitude [91.5] is outside the range a coordinate may take.')
        ->and(InvalidCoordinates::longitudeOutOfRange(-181.25)->getMessage())
        ->toBe('The longitude [-181.25] is outside the range a coordinate may take.');
});

it('quotes the value back, so a log says which point was refused', function () {
    expect(InvalidCoordinates::latitudeOutOfRange(1000.0)->getMessage())->toContain('[1000]');
});

it('answers with one error code however the point fell outside', function (InvalidCoordinates $failure) {
    expect($failure->errorCode())->toBe('invalid_coordinates');
})->with([
    'latitude' => fn () => InvalidCoordinates::latitudeOutOfRange(91.0),
    'longitude' => fn () => InvalidCoordinates::longitudeOutOfRange(181.0),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidCoordinates $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'latitude' => fn () => InvalidCoordinates::latitudeOutOfRange(91.0),
    'longitude' => fn () => InvalidCoordinates::longitudeOutOfRange(181.0),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCoordinates::latitudeOutOfRange(91.0))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCoordinates::latitudeOutOfRange(91.0)->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('says which half of the point was missing, quoting the half that was sent', function () {
    expect(InvalidCoordinates::missingLatitude('-99.1768069')->getMessage())
        ->toBe('The longitude [-99.1768069] was given without a latitude.')
        ->and(InvalidCoordinates::missingLongitude('19.3627888')->getMessage())
        ->toBe('The latitude [19.3627888] was given without a longitude.');
});

it('names each half of a point that is not a finite number', function (float $latitude, float $longitude, string $message) {
    expect(InvalidCoordinates::notFinite($latitude, $longitude)->getMessage())->toBe($message);
})->with([
    'a latitude that is not a number' => [NAN, 0.0, 'The coordinate [latitude NAN, longitude 0] is not a finite number.'],
    'a longitude that is not a number' => [0.0, NAN, 'The coordinate [latitude 0, longitude NAN] is not a finite number.'],
    'positive infinity' => [19.36, INF, 'The coordinate [latitude 19.36, longitude INF] is not a finite number.'],
    'negative infinity' => [-INF, 0.0, 'The coordinate [latitude -INF, longitude 0] is not a finite number.'],
    'neither half finite' => [-INF, NAN, 'The coordinate [latitude -INF, longitude NAN] is not a finite number.'],
]);

it('builds the message for a point that is not a number without raising a warning of its own', function () {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $failure = InvalidCoordinates::notFinite(NAN, INF);
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([])
        ->and($failure->getMessage())->toBe('The coordinate [latitude NAN, longitude INF] is not a finite number.');
});

it('answers with one error code however the point was refused', function (InvalidCoordinates $failure) {
    expect($failure->errorCode())->toBe('invalid_coordinates')
        ->and($failure->kind())->toBe(DomainFailureKind::Invalid)
        ->and($failure)->toBeInstanceOf(DomainFailure::class);
})->with([
    'a missing latitude' => fn () => InvalidCoordinates::missingLatitude('-99.1768069'),
    'a missing longitude' => fn () => InvalidCoordinates::missingLongitude('19.3627888'),
    'a point that is not a number' => fn () => InvalidCoordinates::notFinite(NAN, 0.0),
]);
