<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ExtendSubscriptionInput;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;

it('reads the business and the last included day', function () {
    $input = ExtendSubscriptionInput::fromRequest(['business' => 'barberia-centro', 'until' => '2026-12-31']);

    expect($input->businessSlug()->value)->toBe('barberia-centro')
        ->and($input->lastIncludedDay()->date)->toBe('2026-12-31');
});

it('accepts a well formed payload', function () {
    expect(fn () => ExtendSubscriptionInput::fromRequest(['business' => 'barberia-centro', 'until' => '2026-12-31'])->validate())
        ->not->toThrow(Throwable::class);
});

it('survives a payload with keys missing and refuses it as a domain failure', function () {
    expect(fn () => ExtendSubscriptionInput::fromRequest([])->validate())
        ->toThrow(InvalidSubscriptionBusinessSlug::class);
});

it('rejects a payload the command would have rejected', function (array $payload, string $exception) {
    expect(fn () => ExtendSubscriptionInput::fromRequest($payload)->validate())->toThrow($exception);
})->with([
    'a blank business' => [['business' => ' ', 'until' => '2026-12-31'], InvalidSubscriptionBusinessSlug::class],
    'no end date' => [['business' => 'barberia-centro'], InvalidSubscriptionEndDate::class],
    'a blank end date' => [['business' => 'barberia-centro', 'until' => '  '], InvalidSubscriptionEndDate::class],
    'a malformed end date' => [['business' => 'barberia-centro', 'until' => '2026-12-31 23:59'], InvalidSubscriptionEndDate::class],
    'an impossible end date' => [['business' => 'barberia-centro', 'until' => '2026-04-31'], InvalidSubscriptionEndDate::class],
]);
