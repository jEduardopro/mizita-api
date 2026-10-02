<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\UseCases\EnforcePaymentGrace;
use Illuminate\Console\Command;

final class EnforcePaymentGraceCommand extends Command
{
    protected $signature = 'subscriptions:enforce-payment-grace';

    protected $description = 'Queue a cancellation for every past-due subscription whose payment grace period has run out';

    public function handle(EnforcePaymentGrace $enforcePaymentGrace): int
    {
        /** @var int $scheduled */
        $scheduled = $enforcePaymentGrace->handle()->value();

        $this->info(sprintf('Queued %d cancellation(s) for subscriptions past their payment grace.', $scheduled));

        return self::SUCCESS;
    }
}
