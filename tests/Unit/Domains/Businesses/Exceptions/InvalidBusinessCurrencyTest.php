<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('quotes the code back, so a log says which one was refused', function () {
    expect(InvalidBusinessCurrency::forValue('MX')->getMessage())
        ->toBe('[MX] is not a three letter ISO 4217 currency code.');
});

it('quotes an empty code back as an empty pair of brackets rather than saying nothing', function () {
    expect(InvalidBusinessCurrency::forValue('')->getMessage())
        ->toBe('[] is not a three letter ISO 4217 currency code.');
});

it('answers with a stable error code', function (string $value) {
    expect(InvalidBusinessCurrency::forValue($value)->errorCode())->toBe('invalid_business_currency');
})->with(['too short' => 'MX', 'too long' => 'MXNN', 'not letters' => '484']);

it('classifies the refusal as a 422 rather than a conflict or a 500', function () {
    expect(InvalidBusinessCurrency::forValue('MX')->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidBusinessCurrency::forValue('MX'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidBusinessCurrency::forValue('MX')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
