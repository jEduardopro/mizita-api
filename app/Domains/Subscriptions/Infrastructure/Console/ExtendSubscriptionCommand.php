<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\Dtos\ExtendSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\ExtendSubscription;
use App\Domains\Subscriptions\Infrastructure\Console\Concerns\ReportsFailedUseCase;
use Illuminate\Console\Command;

final class ExtendSubscriptionCommand extends Command
{
    use ReportsFailedUseCase;

    protected $signature = 'subscriptions:extend
        {business : The slug of the business}
        {--until= : The new last day the subscription includes, YYYY-MM-DD in the business timezone}';

    protected $description = 'Extend the subscription a business has in effect to a later local day';

    public function handle(ExtendSubscription $extendSubscription): int
    {
        $response = $extendSubscription->handle(ExtendSubscriptionInput::fromRequest([
            'business' => $this->argument('business'),
            'until' => $this->option('until'),
        ]));

        if ($response->failed()) {
            return $this->reportFailure($response->error());
        }

        /** @var SubscriptionData $subscription */
        $subscription = $response->value();

        $this->info(sprintf(
            'Extended the subscription of business [%s] until %s.',
            $subscription->businessId,
            $subscription->endsAt?->format(DATE_ATOM) ?? 'further notice',
        ));

        return self::SUCCESS;
    }
}
