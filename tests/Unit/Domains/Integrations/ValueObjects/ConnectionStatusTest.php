<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\ConnectionStatus;

it('reads back every status from the string the column stores', function (string $stored, ConnectionStatus $status) {
    expect(ConnectionStatus::from($stored))->toBe($status)
        ->and($status->value)->toBe($stored);
})->with([
    'connected' => ['connected', ConnectionStatus::Connected],
    'needs a reconnect' => ['needs_reconnect', ConnectionStatus::NeedsReconnect],
]);

it('knows no status beyond connected and awaiting a reconnect', function () {
    expect(ConnectionStatus::cases())->toHaveCount(2);
});
