<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use RuntimeException;

final class GoogleApiFailure extends RuntimeException
{
    public static function unreachable(string $method, string $path, string $cause): self
    {
        return new self("Google did not answer {$method} {$path} ({$cause}).");
    }

    public static function unexpectedStatus(string $method, string $path, int $status): self
    {
        return new self("Google answered {$method} {$path} with HTTP {$status}.");
    }

    public static function tokenRefreshFailed(string $connectionId, string $cause): self
    {
        return new self("Refreshing the access token of calendar connection [{$connectionId}] failed ({$cause}).");
    }

    public static function unknownConnection(string $connectionId): self
    {
        return new self("Calendar connection [{$connectionId}] holds no stored credentials.");
    }
}
