<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionInput;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;

it('reads the business', function () {
    expect(CancelSubscriptionInput::fromRequest(['business' => ' barberia-centro '])->businessSlug()->value)
        ->toBe('barberia-centro');
});

it('accepts a well formed payload', function () {
    expect(fn () => CancelSubscriptionInput::fromRequest(['business' => 'barberia-centro'])->validate())
        ->not->toThrow(Throwable::class);
});

it('rejects a missing or blank business as a domain failure', function (array $payload) {
    expect(fn () => CancelSubscriptionInput::fromRequest($payload)->validate())
        ->toThrow(InvalidSubscriptionBusinessSlug::class);
})->with([
    'no key at all' => [[]],
    'empty' => [['business' => '']],
    'spaces' => [['business' => '   ']],
    'null' => [['business' => null]],
]);
