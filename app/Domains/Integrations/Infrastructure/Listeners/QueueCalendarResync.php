<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Listeners;

use App\Domains\Integrations\Application\Dtos\ResumeBusinessCalendarSyncInput;
use App\Domains\Integrations\Application\UseCases\ResumeBusinessCalendarSync;
use App\Domains\Subscriptions\Events\SubscriptionStarted;

final class QueueCalendarResync
{
    public function __construct(
        private readonly ResumeBusinessCalendarSync $resumeCalendarSync,
    ) {}

    public function handle(SubscriptionStarted $event): void
    {
        $this->resumeCalendarSync->handle(new ResumeBusinessCalendarSyncInput($event->businessId));
    }
}
