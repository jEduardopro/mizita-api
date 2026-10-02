<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Auth;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

final class PendingPlatformLogin
{
    public const TIME_TO_LIVE_MINUTES = 5;

    public const SESSION_KEY = 'platform.login.pending';

    public const ADMIN_ID_SESSION_PATH = self::SESSION_KEY.'.'.self::ADMIN_ID_KEY;

    private const ADMIN_ID_KEY = 'admin_uuid';

    private const EXPIRES_AT_KEY = 'expires_at';

    public function __construct(
        private readonly Session $session,
    ) {}

    public function remember(string $adminId, DateTimeImmutable $now): void
    {
        $this->session->put(self::SESSION_KEY, [
            self::ADMIN_ID_KEY => $adminId,
            self::EXPIRES_AT_KEY => $now->add(new DateInterval('PT'.self::TIME_TO_LIVE_MINUTES.'M'))->getTimestamp(),
        ]);
    }

    public function pendingAdminId(DateTimeImmutable $now): ?string
    {
        $marker = $this->session->get(self::SESSION_KEY);

        if (! is_array($marker) || ! is_string($marker[self::ADMIN_ID_KEY] ?? null) || ! is_int($marker[self::EXPIRES_AT_KEY] ?? null)) {
            return null;
        }

        if ($marker[self::EXPIRES_AT_KEY] <= $now->getTimestamp()) {
            $this->forget();

            return null;
        }

        return $marker[self::ADMIN_ID_KEY];
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
