<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Factories;

use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\ValueObjects\BillingInterval;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Shared\ValueObjects\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanModel>
 */
final class PlanModelFactory extends Factory
{
    protected $model = PlanModel::class;

    private const COMPLETE_MONTHLY_PRICE_IN_MINOR_UNITS = 20000;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Plan::Complete->value,
            'name' => 'Completo',
            'price_amount' => self::COMPLETE_MONTHLY_PRICE_IN_MINOR_UNITS,
            'price_currency' => CurrencyCode::default()->value,
            'billing_interval' => BillingInterval::Month->value,
            'trial_days' => null,
            'stripe_price_id' => 'price_'.$this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
        ];
    }
}
