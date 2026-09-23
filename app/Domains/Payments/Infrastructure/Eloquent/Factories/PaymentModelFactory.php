<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Factories;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use App\Shared\ValueObjects\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentModel>
 */
final class PaymentModelFactory extends Factory
{
    private const MINIMUM_TOTAL_UNITS = 1;

    private const MAXIMUM_TOTAL_UNITS = 500;

    private const CENTS_PER_UNIT = 100;

    protected $model = PaymentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'appointment_id' => fn (): int => AppointmentModel::factory()->create()->id,
            'currency_code' => CurrencyCode::default()->value,
            'discount_amount_cents' => 0,
            'total_cents' => fake()->numberBetween(self::MINIMUM_TOTAL_UNITS, self::MAXIMUM_TOTAL_UNITS)
                * self::CENTS_PER_UNIT,
            'paid_cents' => 0,
        ];
    }

    public function discountedBy(int $cents): self
    {
        return $this->state(fn (array $attributes): array => [
            'discount_amount_cents' => $cents,
            'total_cents' => $attributes['total_cents'] - $cents,
        ]);
    }

    public function paidInFull(): self
    {
        return $this->state(fn (array $attributes): array => [
            'paid_cents' => $attributes['total_cents'],
        ]);
    }

    public function partiallyPaid(int $cents): self
    {
        return $this->state(fn (): array => ['paid_cents' => $cents]);
    }
}
