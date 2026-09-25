<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\TwoFactorStatus;

it('knows exactly three states, backed by the values the client reads', function () {
    expect(array_map(static fn (TwoFactorStatus $status): string => $status->value, TwoFactorStatus::cases()))
        ->toBe(['disabled', 'pending', 'enabled']);
});

it('asks for a second factor only once the setup was confirmed', function (TwoFactorStatus $status, bool $requires) {
    expect($status->requiresSecondFactor())->toBe($requires);
})->with([
    'never set up' => [TwoFactorStatus::Disabled, false],
    'set up but never confirmed with a code' => [TwoFactorStatus::Pending, false],
    'confirmed' => [TwoFactorStatus::Enabled, true],
]);
