<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\UseCases\ExpireSubscriptions;
use App\Domains\Subscriptions\Infrastructure\Console\Concerns\ReportsFailedUseCase;
use Illuminate\Console\Command;

final class ExpireSubscriptionsCommand extends Command
{
    use ReportsFailedUseCase;

    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark every active subscription whose period has ended as expired';

    public function handle(ExpireSubscriptions $expireSubscriptions): int
    {
        $response = $expireSubscriptions->handle();

        if ($response->failed()) {
            return $this->reportFailure($response->error());
        }

        /** @var int $expired */
        $expired = $response->value();

        $this->info(sprintf('Expired %d subscription(s).', $expired));

        return self::SUCCESS;
    }
}
