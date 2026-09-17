<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Factories;

use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddressModel>
 */
final class AddressModelFactory extends Factory
{
    protected $model = AddressModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addressable_type' => AddressOwnerType::Business->value,
            'addressable_id' => fake()->numberBetween(1, 100000),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state_id' => null,
            'postal_code' => (string) fake()->numberBetween(10000, 99999),
            'country_code' => 'MX',
            'latitude' => null,
            'longitude' => null,
        ];
    }

    public function withoutCity(): self
    {
        return $this->state(fn (): array => ['city' => null]);
    }

    public function withoutPostalCode(): self
    {
        return $this->state(fn (): array => ['postal_code' => null]);
    }

    public function streetOnly(): self
    {
        return $this->state(fn (): array => [
            'city' => null,
            'state_id' => null,
            'postal_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
