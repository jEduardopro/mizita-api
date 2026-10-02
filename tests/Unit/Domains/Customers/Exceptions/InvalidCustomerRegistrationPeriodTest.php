<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

dataset('registration period refusals', fn () => [
    'malformed' => [InvalidCustomerRegistrationPeriod::malformed('2026-3-1')],
    'incomplete' => [InvalidCustomerRegistrationPeriod::incomplete()],
    'inverted' => [InvalidCustomerRegistrationPeriod::inverted('2026-03-02', '2026-03-01')],
    'too wide' => [InvalidCustomerRegistrationPeriod::tooWide('2020-01-01', '2026-01-01', 5)],
]);

it('quotes the date it could not read', function () {
    expect(InvalidCustomerRegistrationPeriod::malformed('2026-3-1')->getMessage())
        ->toBe('The registration date [2026-3-1] is not a calendar date in the YYYY-MM-DD form.');
});

it('says a period needs both of its bounds', function () {
    expect(InvalidCustomerRegistrationPeriod::incomplete()->getMessage())
        ->toBe('A registration period needs both a from and a to date, or neither.');
});

it('names both dates of a reversed period', function () {
    expect(InvalidCustomerRegistrationPeriod::inverted('2026-03-02', '2026-03-01')->getMessage())
        ->toBe('A registration period has to start on or before it ends, got [2026-03-02] to [2026-03-01].');
});

it('names both dates of a period wider than the cap, and the cap itself', function () {
    expect(InvalidCustomerRegistrationPeriod::tooWide('2020-01-01', '2026-01-01', 5)->getMessage())
        ->toBe('A registration period may span at most [5] years, got [2020-01-01] to [2026-01-01].');
});

it('answers with one stable error code whatever the reason', function (InvalidCustomerRegistrationPeriod $refusal) {
    expect($refusal->errorCode())->toBe('invalid_customer_registration_period');
})->with('registration period refusals');

it('classifies every reason as a 422', function (InvalidCustomerRegistrationPeriod $refusal) {
    expect($refusal->kind())->toBe(DomainFailureKind::Invalid);
})->with('registration period refusals');

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerRegistrationPeriod::incomplete())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerRegistrationPeriod::incomplete()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
