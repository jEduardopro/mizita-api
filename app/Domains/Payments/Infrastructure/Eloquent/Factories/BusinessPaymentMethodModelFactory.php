<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\BusinessPaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessPaymentMethodModel>
 */
final class BusinessPaymentMethodModelFactory extends Factory
{
    protected $model = BusinessPaymentMethodModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'payment_method_id' => fn (): int => PaymentMethodModel::factory()->create()->id,
            'enabled' => true,
            'position' => 0,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
