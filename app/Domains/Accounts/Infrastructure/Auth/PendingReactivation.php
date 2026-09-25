<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

final class PendingReactivation
{
    public const TIME_TO_LIVE_MINUTES = 15;

    private const SESSION_KEY = 'accounts.pending_reactivation';

    private const ACCOUNT_ID_KEY = 'account_id';

    private const EXPIRES_AT_KEY = 'expires_at';

    public function __construct(
        private readonly Session $session,
    ) {}

    public function remember(string $accountId, DateTimeImmutable $now): void
    {
        $this->session->put(self::SESSION_KEY, [
            self::ACCOUNT_ID_KEY => $accountId,
            self::EXPIRES_AT_KEY => $now->add(new DateInterval('PT'.self::TIME_TO_LIVE_MINUTES.'M'))->getTimestamp(),
        ]);
    }

    public function pendingAccountId(DateTimeImmutable $now): ?string
    {
        $marker = $this->session->get(self::SESSION_KEY);

        if (! is_array($marker) || ! is_string($marker[self::ACCOUNT_ID_KEY] ?? null) || ! is_int($marker[self::EXPIRES_AT_KEY] ?? null)) {
            return null;
        }

        if ($marker[self::EXPIRES_AT_KEY] <= $now->getTimestamp()) {
            $this->forget();

            return null;
        }

        return $marker[self::ACCOUNT_ID_KEY];
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
