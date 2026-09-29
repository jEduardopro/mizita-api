<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
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

    private const STRIPE_ID_SHAPE = '[A-Za-z0-9]{24}';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'business_id' => BusinessModel::factory(),
            'plan_id' => fn (): int => $this->completePlanId(),
            'status' => SubscriptionStatus::Active->value,
            'stripe_customer_id' => 'cus_'.$this->faker->unique()->regexify(self::STRIPE_ID_SHAPE),
            'stripe_subscription_id' => 'sub_'.$this->faker->unique()->regexify(self::STRIPE_ID_SHAPE),
            'started_at' => $startedAt,
            'current_period_ends_at' => $startedAt->add(new DateInterval(self::PERIOD_LENGTH)),
            'canceled_at' => null,
            'payment_failed_at' => null,
        ];
    }

    private function completePlanId(): int
    {
        $existingPlanId = PlanModel::query()
            ->where('key', Plan::Complete->value)
            ->value('id');

        if ($existingPlanId !== null) {
            return (int) $existingPlanId;
        }

        return PlanModel::factory()->create()->id;
    }
}
