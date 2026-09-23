<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Factories;

use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentTransactionModel;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransactionModel>
 */
final class PaymentTransactionModelFactory extends Factory
{
    private const MINIMUM_AMOUNT_UNITS = 1;

    private const MAXIMUM_AMOUNT_UNITS = 200;

    private const CENTS_PER_UNIT = 100;

    protected $model = PaymentTransactionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => fn (): int => PaymentModel::factory()->create()->id,
            'payment_method_id' => fn (): int => PaymentMethodModel::factory()->create()->id,
            'account_id' => null,
            'type' => PaymentTransactionType::Approved,
            'subtotal_pre_discount_cents' => 0,
            'discount_type' => DiscountType::None,
            'discount_value' => 0,
            'subtotal_discount_cents' => 0,
            'subtotal_cents' => 0,
            'total_cents' => fake()->numberBetween(self::MINIMUM_AMOUNT_UNITS, self::MAXIMUM_AMOUNT_UNITS)
                * self::CENTS_PER_UNIT,
            'external_reference' => null,
            'processed_at' => (new DateTimeImmutable)->format(DATE_ATOM),
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => ['type' => PaymentTransactionType::Approved]);
    }

    public function voided(User $account): self
    {
        return $this->state(fn (): array => [
            'type' => PaymentTransactionType::Void,
            'account_id' => $account->id,
        ]);
    }
}
