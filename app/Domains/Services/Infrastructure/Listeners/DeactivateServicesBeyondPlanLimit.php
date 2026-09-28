<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Listeners;

use App\Domains\Services\Application\Dtos\EnforceActiveServiceLimitInput;
use App\Domains\Services\Application\UseCases\EnforceActiveServiceLimit;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class DeactivateServicesBeyondPlanLimit implements ShouldQueueAfterCommit
{
    private const BACKOFF_SECONDS = [30, 120];

    public int $tries = 3;

    public function __construct(
        private readonly EnforceActiveServiceLimit $enforceLimit,
    ) {}

    public function handle(SubscriptionEnded $event): void
    {
        $this->enforceLimit->handle(new EnforceActiveServiceLimitInput($event->businessId))->value();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
