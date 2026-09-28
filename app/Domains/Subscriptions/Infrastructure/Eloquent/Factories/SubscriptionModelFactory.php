<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionModel>
 */
final class SubscriptionModelFactory extends Factory
{
    protected $model = SubscriptionModel::class;

    private const PERIOD_LENGTH = 'P1M';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $listPrice = Plan::Complete->listPrice();

        return [
            'business_id' => BusinessModel::factory(),
            'plan' => Plan::Complete->value,
            'status' => SubscriptionStatus::Active->value,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->add(new DateInterval(self::PERIOD_LENGTH)),
            'price_amount' => $listPrice->amountInMinorUnits,
            'price_currency' => $listPrice->currency->value,
        ];
    }
}
