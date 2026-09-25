<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Authorization;

use App\Domains\Integrations\Contracts\CalendarAuthorizationStates;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use Illuminate\Contracts\Cache\Repository as Cache;
use SensitiveParameter;

final class CacheCalendarAuthorizationStates implements CalendarAuthorizationStates
{
    private const STATE_BYTES = 32;

    private const TIME_TO_LIVE_SECONDS = 600;

    private const PENDING_PREFIX = 'integrations:calendar-authorization:pending:';

    private const CONSUMED_PREFIX = 'integrations:calendar-authorization:consumed:';

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function issue(PendingAuthorization $pending): string
    {
        $state = bin2hex(random_bytes(self::STATE_BYTES));

        $this->cache->put(
            self::PENDING_PREFIX.self::fingerprintOf($state),
            [
                'account_id' => $pending->accountId,
                'business_id' => $pending->businessId,
                'staff_member_id' => $pending->staffMemberId,
            ],
            self::TIME_TO_LIVE_SECONDS,
        );

        return $state;
    }

    public function consume(#[SensitiveParameter] string $state): PendingAuthorization
    {
        $fingerprint = self::fingerprintOf($state);

        if (! $this->cache->add(self::CONSUMED_PREFIX.$fingerprint, true, self::TIME_TO_LIVE_SECONDS)) {
            throw CalendarAuthorizationStateInvalid::expiredOrUsed();
        }

        $payload = $this->cache->pull(self::PENDING_PREFIX.$fingerprint);

        if (! is_array($payload)) {
            throw CalendarAuthorizationStateInvalid::expiredOrUsed();
        }

        return new PendingAuthorization(
            accountId: (string) ($payload['account_id'] ?? ''),
            businessId: (string) ($payload['business_id'] ?? ''),
            staffMemberId: (string) ($payload['staff_member_id'] ?? ''),
        );
    }

    private static function fingerprintOf(#[SensitiveParameter] string $state): string
    {
        return hash('sha256', $state);
    }
}
