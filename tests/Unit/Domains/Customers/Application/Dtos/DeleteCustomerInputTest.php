<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\DeleteCustomerInput;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('carries the customer the route named', function () {
    expect((new DeleteCustomerInput('01930000-0000-7000-8000-0000000000c1'))->customerId)
        ->toBe('01930000-0000-7000-8000-0000000000c1');
});

it('accepts a uuid however it is cased', function (string $id) {
    expect(fn () => (new DeleteCustomerInput($id))->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => '01930000-0000-7000-8000-0000000000c1',
    'uppercase' => '01930000-0000-7000-8000-0000000000C1',
    'a version four uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
]);

it('refuses an id no customer could ever have, before anything is removed', function (string $id) {
    expect(fn () => (new DeleteCustomerInput($id))->validate())->toThrow(CustomerNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '1',
    'every customer at once' => '*',
    'a uuid missing a block' => '01930000-0000-7000-0000000000c1',
    'a uuid without hyphens' => '019300000000700080000000000000c1',
    'padded' => ' 01930000-0000-7000-8000-0000000000c1 ',
    'a trailing newline' => "01930000-0000-7000-8000-0000000000c1\n",
    'sql' => "01930000-0000-7000-8000-0000000000c1' or '1'='1",
]);

it('refuses with a failure the responder renders as a missing page rather than a bad request', function () {
    $refusal = null;

    try {
        (new DeleteCustomerInput('not-a-uuid'))->validate();
    } catch (CustomerNotFound $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('customer_not_found')
        ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
});
