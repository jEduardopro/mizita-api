<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Factories;

use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumberType;
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
            'phoneable_id' => 1,
            'country_code' => CountryCode::Mx->value,
            'national_number' => '8121001069',
            'calling_code' => 52,
            'e164' => '+528121001069',
            'number_type' => PhoneNumberType::FixedLineOrMobile->value,
            'geo_description' => 'Monterrey, NL',
            'timezones' => ['America/Mexico_City'],
        ];
    }

    public function ownedBy(PhoneOwnerType $ownerType, int $ownerKey): self
    {
        return $this->state(fn (): array => [
            'phoneable_type' => $ownerType->value,
            'phoneable_id' => $ownerKey,
        ]);
    }
}
