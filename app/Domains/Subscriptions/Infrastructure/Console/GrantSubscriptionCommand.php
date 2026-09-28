<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console;

use App\Domains\Subscriptions\Application\Dtos\GrantSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\GrantSubscription;
use App\Domains\Subscriptions\Infrastructure\Console\Concerns\ReportsFailedUseCase;
use Illuminate\Console\Command;

final class GrantSubscriptionCommand extends Command
{
    use ReportsFailedUseCase;

    protected $signature = 'subscriptions:grant
        {business : The slug of the business}
        {--until= : The last day the subscription includes, YYYY-MM-DD in the business timezone}
        {--plan=complete : The plan to grant}
        {--amount= : The price charged, in minor currency units; defaults to the plan list price}';

    protected $description = 'Grant a business a subscription starting now and ending after the given local day';

    public function handle(GrantSubscription $grantSubscription): int
    {
        $response = $grantSubscription->handle(GrantSubscriptionInput::fromRequest([
            'business' => $this->argument('business'),
            'until' => $this->option('until'),
            'plan' => $this->option('plan'),
            'amount' => $this->option('amount'),
        ]));

        if ($response->failed()) {
            return $this->reportFailure($response->error());
        }

        /** @var SubscriptionData $subscription */
        $subscription = $response->value();

        $this->info(sprintf(
            'Granted the %s plan to business [%s] until %s.',
            $subscription->plan,
            $subscription->businessId,
            $subscription->endsAt?->format(DATE_ATOM) ?? 'further notice',
        ));

        return self::SUCCESS;
    }
}
