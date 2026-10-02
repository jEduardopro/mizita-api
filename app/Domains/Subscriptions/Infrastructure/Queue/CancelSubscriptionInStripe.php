<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Queue;

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionPastPaymentGraceInput;
use App\Domains\Subscriptions\Application\UseCases\CancelSubscriptionPastPaymentGrace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class CancelSubscriptionInStripe implements ShouldQueue
{
    use Queueable;

    private const BACKOFF_SECONDS = [30, 120, 600];

    private const RELEASE_WHILE_LOCKED_SECONDS = 10;

    private const LOCK_EXPIRES_AFTER_SECONDS = 120;

    public int $tries = 4;

    public function __construct(
        public readonly string $businessId,
    ) {}

    public function handle(CancelSubscriptionPastPaymentGrace $cancel): void
    {
        $cancel->handle(new CancelSubscriptionPastPaymentGraceInput($this->businessId));
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->businessId))
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
