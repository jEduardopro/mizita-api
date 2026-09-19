<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Eloquent\Factories;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\Infrastructure\RandomManageTokenFactory;
use App\Domains\Appointments\Infrastructure\RandomReferenceCodeGenerator;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentModel>
 */
final class AppointmentModelFactory extends Factory
{
    private const DEFAULT_DURATION_MINUTES = 30;

    private const MANAGE_TOKEN_LIFETIME = '+60 days';

    protected $model = AppointmentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = DateTimeImmutable::createFromInterface(
            fake()->dateTimeBetween('+1 day', '+30 days'),
        )->setTime((int) fake()->numberBetween(9, 17), 0);

        return [
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'customer_id' => fn (): int => CustomerModel::factory()->create()->id,
            'service_id' => fn (): int => ServiceModel::factory()->create()->id,
            'staff_member_id' => fn (): int => StaffMemberModel::factory()->create()->id,
            'starts_at' => $startsAt->format(DATE_ATOM),
            'ends_at' => $startsAt->modify('+'.self::DEFAULT_DURATION_MINUTES.' minutes')->format(DATE_ATOM),
            'notes' => fake()->sentence(),
            'reference_code' => (new RandomReferenceCodeGenerator)->next()->value,
            'manage_token_hash' => null,
            'manage_token_expires_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'source' => BookingSource::Admin,
        ];
    }

    public function withoutNotes(): self
    {
        return $this->state(fn (): array => ['notes' => null]);
    }

    public function bookedAsGuest(): self
    {
        return $this->state(fn (): array => [
            'manage_token_hash' => (new RandomManageTokenFactory)->issue()->hash(),
            'manage_token_expires_at' => (new DateTimeImmutable(self::MANAGE_TOKEN_LIFETIME))->format(DATE_ATOM),
            'source' => BookingSource::Public,
        ]);
    }

    public function cancelledBy(Canceller $canceller): self
    {
        return $this->state(fn (): array => [
            'cancelled_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            'cancelled_by' => $canceller,
        ]);
    }
}
