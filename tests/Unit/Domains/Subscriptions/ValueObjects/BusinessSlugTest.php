<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionBusinessSlug;
use App\Domains\Subscriptions\ValueObjects\BusinessSlug;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('keeps the slug trimmed', function (string $value) {
    expect(BusinessSlug::fromString($value)->value)->toBe('barberia-centro');
})->with(['bare' => 'barberia-centro', 'padded' => '  barberia-centro  ', 'newline' => "barberia-centro\n"]);

it('keeps accents and unicode, leaving whether the slug exists to the directory', function () {
    expect(BusinessSlug::fromString('peluquería-ñandú')->value)->toBe('peluquería-ñandú');
});

it('rejects a missing slug', function (string $value) {
    expect(fn () => BusinessSlug::fromString($value))->toThrow(InvalidSubscriptionBusinessSlug::class);
})->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

it('refuses a missing slug as an invalid domain failure', function () {
    try {
        BusinessSlug::fromString('');
    } catch (InvalidSubscriptionBusinessSlug $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('invalid_business_slug')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

        return;
    }

    $this->fail('A blank slug was accepted.');
});
