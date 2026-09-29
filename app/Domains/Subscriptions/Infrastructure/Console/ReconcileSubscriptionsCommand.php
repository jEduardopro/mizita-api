<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\UseCases\ReconcileLapsedSubscriptions;
use Illuminate\Console\Command;

final class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:reconcile';

    protected $description = 'Queue a billing sync for every subscription whose paid period lapsed without an ending being recorded';

    public function handle(ReconcileLapsedSubscriptions $reconcile): int
    {
        /** @var int $scheduled */
        $scheduled = $reconcile->handle()->value();

        $this->info(sprintf('Queued %d subscription(s) for a billing sync.', $scheduled));

        return self::SUCCESS;
    }
}
