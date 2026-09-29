<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Queue;

use App\Domains\Subscriptions\Application\Dtos\SyncSubscriptionFromBillingInput;
use App\Domains\Subscriptions\Application\UseCases\SyncSubscriptionFromBilling;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class SyncSubscriptionFromStripe implements ShouldQueueAfterCommit
{
    use Queueable;

    private const BACKOFF_SECONDS = [30, 120, 600];

    private const RELEASE_WHILE_LOCKED_SECONDS = 10;

    private const LOCK_EXPIRES_AFTER_SECONDS = 120;

    public int $tries = 8;

    public function __construct(
        public readonly string $billingSubscriptionId,
    ) {}

    public function handle(SyncSubscriptionFromBilling $sync): void
    {
        $sync->handle(new SyncSubscriptionFromBillingInput($this->billingSubscriptionId));
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->billingSubscriptionId))
                ->releaseAfter(self::RELEASE_WHILE_LOCKED_SECONDS)
                ->expireAfter(self::LOCK_EXPIRES_AFTER_SECONDS),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
