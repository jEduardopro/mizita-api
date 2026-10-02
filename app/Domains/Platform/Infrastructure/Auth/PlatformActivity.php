<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

final class PlatformActivity
{
    public const IDLE_TIMEOUT_MINUTES = 30;

    public const SESSION_KEY = 'platform.last_activity';

    private const SECONDS_PER_MINUTE = 60;

    public function __construct(
        private readonly Session $session,
    ) {}

    public function touch(DateTimeImmutable $now): void
    {
        $this->session->put(self::SESSION_KEY, $now->getTimestamp());
    }

    public function isIdleAt(DateTimeImmutable $now): bool
    {
        $lastActivity = $this->session->get(self::SESSION_KEY);

        if (! is_int($lastActivity)) {
            return true;
        }

        return $now->getTimestamp() - $lastActivity > self::IDLE_TIMEOUT_MINUTES * self::SECONDS_PER_MINUTE;
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
