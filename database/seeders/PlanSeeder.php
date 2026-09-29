<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Shared\Contracts\IdGenerator;
use App\Shared\ValueObjects\CurrencyCode;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    private const COMPLETE_NAME = 'Completo';

    private const COMPLETE_MONTHLY_PRICE_IN_MINOR_UNITS = 20000;

    private const COMPLETE_MONTHLY_PRICE_CONFIG = 'services.stripe.prices.complete_monthly';

    public function __construct(
        private readonly IdGenerator $ids,
    ) {}

    public function run(): void
    {
        $stripePriceId = trim((string) config(self::COMPLETE_MONTHLY_PRICE_CONFIG));

        if ($stripePriceId === '') {
            $this->command?->warn(
                'Skipped the Complete plan: set STRIPE_PRICE_COMPLETE_MONTHLY to its Stripe price id and seed again.',
            );

            return;
        }

        $this->upsertComplete($stripePriceId);
    }

    private function upsertComplete(string $stripePriceId): void
    {
        $plan = PlanModel::query()->firstOrNew(['key' => Plan::Complete->value]);

        $plan->uuid ??= $this->ids->next();
        $plan->name = self::COMPLETE_NAME;
        $plan->price_amount = self::COMPLETE_MONTHLY_PRICE_IN_MINOR_UNITS;
        $plan->price_currency = CurrencyCode::default()->value;
        $plan->billing_interval = BillingInterval::Month->value;
        $plan->trial_days = null;
        $plan->stripe_price_id = $stripePriceId;

        $plan->save();
    }
}
