<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Factories;

use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethodModel>
 */
final class PaymentMethodModelFactory extends Factory
{
    private const CODE_PATTERN = 'method_????';

    private const BANK_TRANSFER_POSITION = 1;

    protected $model = PaymentMethodModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify(self::CODE_PATTERN),
            'position' => 0,
            'active' => true,
            'requires_integration' => false,
        ];
    }

    public function cash(): self
    {
        return $this->state(fn (): array => [
            'code' => PaymentMethodCode::Cash->value,
            'position' => 0,
        ]);
    }

    public function bankTransfer(): self
    {
        return $this->state(fn (): array => [
            'code' => PaymentMethodCode::BankTransfer->value,
            'position' => self::BANK_TRANSFER_POSITION,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function requiringIntegration(): self
    {
        return $this->state(fn (): array => ['requires_integration' => true]);
    }
}
