<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Factories;

use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentItemModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentItemModel>
 */
final class PaymentItemModelFactory extends Factory
{
    private const MINIMUM_AMOUNT_UNITS = 1;

    private const MAXIMUM_AMOUNT_UNITS = 200;

    private const CENTS_PER_UNIT = 100;

    protected $model = PaymentItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => fn (): int => PaymentModel::factory()->create()->id,
            'name' => fake()->words(2, true),
            'amount_cents' => fake()->numberBetween(self::MINIMUM_AMOUNT_UNITS, self::MAXIMUM_AMOUNT_UNITS)
                * self::CENTS_PER_UNIT,
            'position' => 0,
        ];
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
