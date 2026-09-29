<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\UseCases\EnforcePaymentGrace;
use Illuminate\Console\Command;

final class EnforcePaymentGraceCommand extends Command
{
    protected $signature = 'subscriptions:enforce-payment-grace';

    protected $description = 'Cancel every past-due subscription whose payment grace period has run out';

    public function handle(EnforcePaymentGrace $enforcePaymentGrace): int
    {
        /** @var int $canceled */
        $canceled = $enforcePaymentGrace->handle()->value();

        $this->info(sprintf('Canceled %d subscription(s) past their payment grace.', $canceled));

        return self::SUCCESS;
    }
}
