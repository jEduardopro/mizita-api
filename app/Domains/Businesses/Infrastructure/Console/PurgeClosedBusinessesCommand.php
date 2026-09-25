<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Console;

use App\Domains\Businesses\Application\UseCases\FindBusinessesDueForPurge;
use App\Domains\Businesses\Infrastructure\Queue\PurgeClosedBusinessJob;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;

final class PurgeClosedBusinessesCommand extends Command
{
    protected $signature = 'businesses:purge-closed';

    protected $description = 'Queue one purge job per closed business whose retention period has elapsed';

    public function handle(FindBusinessesDueForPurge $findBusinessesDueForPurge, Dispatcher $jobs): int
    {
        /** @var list<string> $businessIds */
        $businessIds = $findBusinessesDueForPurge->handle()->value();

        foreach ($businessIds as $businessId) {
            $jobs->dispatch(new PurgeClosedBusinessJob($businessId));
        }

        $this->info(sprintf('Queued the purge of %d closed business(es).', count($businessIds)));

        return self::SUCCESS;
    }
}
