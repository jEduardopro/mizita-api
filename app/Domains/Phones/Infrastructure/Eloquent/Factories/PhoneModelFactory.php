<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Factories;

use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhoneModel>
 */
final class PhoneModelFactory extends Factory
{
    protected $model = PhoneModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phoneable_type' => PhoneOwnerType::Business->value,
            'phoneable_id' => fake()->uuid(),
            'country_code' => fake()->randomElement(CountryCode::cases())->value,
            'national_number' => (string) fake()->numerify('##########'),
        ];
    }

    public function ownedBy(PhoneOwnerType $ownerType, string $ownerId): self
    {
        return $this->state(fn (): array => [
            'phoneable_type' => $ownerType->value,
            'phoneable_id' => $ownerId,
        ]);
    }
}
