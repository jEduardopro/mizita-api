<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\CancelSubscription;
use App\Domains\Subscriptions\Infrastructure\Console\Concerns\ReportsFailedUseCase;
use Illuminate\Console\Command;

final class CancelSubscriptionCommand extends Command
{
    use ReportsFailedUseCase;

    protected $signature = 'subscriptions:cancel
        {business : The slug of the business}';

    protected $description = 'Cancel the subscription a business has in effect, ending it now';

    public function handle(CancelSubscription $cancelSubscription): int
    {
        $response = $cancelSubscription->handle(CancelSubscriptionInput::fromRequest([
            'business' => $this->argument('business'),
        ]));

        if ($response->failed()) {
            return $this->reportFailure($response->error());
        }

        /** @var SubscriptionData $subscription */
        $subscription = $response->value();

        $this->info(sprintf('Canceled the subscription of business [%s].', $subscription->businessId));

        return self::SUCCESS;
    }
}
