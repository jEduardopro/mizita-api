<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Models\User;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CopDemoHistorySeeder extends Seeder
{
    private const BUSINESS_SLUG = 'cop';

    private const DEMO_EMAIL_DOMAIN = 'demo.mizita.test';

    private const RANDOM_SEED = 20260701;

    private const HISTORY_STARTS_ON = '2026-07-01';

    private const UPCOMING_DAYS = 7;

    private const UTC = 'UTC';

    private const ROLE_ASSIGNMENTS_TABLE = 'model_has_roles';

    private const SLOT_STEP_MINUTES = 15;

    private const SECONDS_PER_MINUTE = 60;

    private const SECONDS_PER_HOUR = 3_600;

    private const SECONDS_PER_DAY = 86_400;

    private const PERCENT = 100;

    private const GAP_RATE = 22;

    private const MAXIMUM_GAP_STEPS = 3;

    private const CANCELLATION_RATE = 12;

    private const CUSTOMER_CANCELLATION_RATE = 65;

    private const PUBLIC_SOURCE_RATE = 25;

    private const NOTES_RATE = 15;

    private const FIRST_VISIT_PRIORITY_RATE = 50;

    private const LOYALTY_SKEW = 1.7;

    private const FOUNDING_CUSTOMERS = 12;

    private const LAST_CUSTOMER_JOINS_ON = '2026-09-22';

    private const CUSTOMER_JOINS_AT = '09:00';

    private const MAXIMUM_BOOKING_LEAD_DAYS = 12;

    private const MINIMUM_BOOKING_LEAD_HOURS = 1;

    private const MAXIMUM_BOOKING_LEAD_HOURS = 10;

    private const MAXIMUM_RECENT_BOOKING_HOURS = 72;

    private const MINIMUM_CANCELLATION_FRACTION = 20;

    private const MAXIMUM_CANCELLATION_FRACTION = 90;

    private const PAYMENT_RATE = 90;

    private const DISCOUNT_RATE = 12;

    private const PERCENTAGE_DISCOUNT_RATE = 60;

    private const PARTIAL_PAYMENT_RATE = 11;

    private const VOIDED_PAYMENT_RATE = 7;

    private const CASH_RATE = 65;

    private const STAFF_SELF_COLLECTION_RATE = 30;

    private const PARTIAL_MINIMUM_PERCENT = 30;

    private const PARTIAL_MAXIMUM_PERCENT = 70;

    private const PARTIAL_ROUNDING_CENTS = 1_000;

    private const MINIMUM_VOID_DELAY_MINUTES = 3;

    private const MAXIMUM_VOID_DELAY_MINUTES = 10;

    private const MINIMUM_RETAKE_DELAY_MINUTES = 1;

    private const MAXIMUM_RETAKE_DELAY_MINUTES = 4;

    private const BACKDATE_CHUNK_SIZE = 500;

    /**
     * @var list<int>
     */
    private const PERCENTAGE_DISCOUNTS_BASIS_POINTS = [1_000, 1_500, 2_000];

    /**
     * @var list<int>
     */
    private const FIXED_DISCOUNTS_CENTS = [2_000, 3_000, 5_000];

    private const DEFAULT_SERVICE_WEIGHT = 10;

    /**
     * @var array<int, array{0: int, 1: int}>
     */
    private const MONTHLY_FILL_PERCENT = [
        7 => [48, 60],
        8 => [56, 68],
        9 => [63, 75],
    ];

    /**
     * @var array{0: int, 1: int}
     */
    private const DEFAULT_FILL_PERCENT = [50, 65];

    /**
     * @var array{0: int, 1: int}
     */
    private const UPCOMING_FILL_PERCENT = [30, 50];

    /**
     * @var list<array{name: string, slug: string, duration: int, buffer: int, price: string, color: ServiceColor}>
     */
    private const DEMO_SERVICES = [
        ['name' => 'Barba', 'slug' => 'barba', 'duration' => 30, 'buffer' => 5, 'price' => '120.00', 'color' => ServiceColor::Teal],
        ['name' => 'Corte y barba', 'slug' => 'corte-y-barba', 'duration' => 75, 'buffer' => 10, 'price' => '260.00', 'color' => ServiceColor::Blue],
        ['name' => 'Tinte', 'slug' => 'tinte', 'duration' => 90, 'buffer' => 15, 'price' => '450.00', 'color' => ServiceColor::Purple],
    ];

    /**
     * @var array<string, int>
     */
    private const SERVICE_WEIGHTS = [
        'corte-de-pelo' => 45,
        'barba' => 20,
        'corte-y-barba' => 25,
        'tinte' => 10,
    ];

    /**
     * @var list<string>
     */
    private const CUSTOMER_NAMES = [
        'Alejandro Garza', 'María Fernanda López', 'José Luis Martínez', 'Daniela Treviño',
        'Luis Ángel Hernández', 'Sofía Villarreal', 'Carlos Eduardo Ramírez', 'Valeria Cantú',
        'Jorge Alberto Salinas', 'Ana Paula González', 'Miguel Ángel Flores', 'Regina Elizondo',
        'Diego Rodríguez', 'Ximena Torres', 'Juan Pablo Castillo', 'Andrea Morales',
        'Ricardo Guerra', 'Fernanda Ríos', 'Óscar Medina', 'Paola Sánchez',
        'Emiliano Chapa', 'Camila Reyes', 'Roberto Leal', 'Mariana Cavazos',
        'Héctor Zambrano', 'Lucía Mendoza', 'Arturo Benavides', 'Karla Montemayor',
        'Santiago Delgado', 'Gabriela Peña', 'Iván Ortiz', 'Natalia Aguilar',
        'Rodrigo Tamez', 'Alejandra Vargas', 'Fernando Quiroga', 'Renata Salazar',
        'Mauricio Garza', 'Verónica Luna', 'Eduardo Sepúlveda', 'Itzel Ramos',
    ];

    /**
     * @var list<string>
     */
    private const APPOINTMENT_NOTES = [
        'Degradado bajo, dejar largo arriba',
        'Prefiere tijera, nada de máquina',
        'Viene con su hijo, puede llegar 5 minutos tarde',
        'Recordar el tono del tinte anterior',
        'Barba perfilada, sin rasurar el cuello',
        'Primera vez, preguntar cómo lo quiere',
    ];

    private BusinessModel $business;

    private DateTimeZone $timezone;

    private DateTimeImmutable $now;

    private CurrencyCode $currency;

    private string $ownerAccountId;

    /**
     * @var array<string, true>
     */
    private array $takenReferenceCodes = [];

    /**
     * @var array<int, list<array{0: int, 1: int}>>
     */
    private array $busyIntervals = [];

    /**
     * @var list<array{key: int, joinsOn: string}>
     */
    private array $customers = [];

    /**
     * @var array<int, true>
     */
    private array $visitedCustomers = [];

    public function __construct(
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly PaymentRepository $payments,
    ) {}

    public function run(): void
    {
        $business = BusinessModel::query()->where('slug', self::BUSINESS_SLUG)->first();

        if ($business === null) {
            $this->command?->warn(sprintf('Business "%s" not found; nothing was seeded.', self::BUSINESS_SLUG));

            return;
        }

        if ($this->alreadySeeded($business)) {
            $this->command?->info(sprintf('Demo history already exists for "%s"; skipping.', self::BUSINESS_SLUG));

            return;
        }

        mt_srand(self::RANDOM_SEED);

        DB::transaction(fn () => $this->seedHistory($business));
    }

    private function alreadySeeded(BusinessModel $business): bool
    {
        return CustomerModel::query()
            ->where('business_id', $business->id)
            ->where('email', 'like', '%@'.self::DEMO_EMAIL_DOMAIN)
            ->exists();
    }

    private function seedHistory(BusinessModel $business): void
    {
        $this->business = $business;
        $this->timezone = new DateTimeZone($business->timezone);
        $this->now = $this->clock->now();
        $this->currency = CurrencyCode::fromString($business->currency_code);
        $this->ownerAccountId = $this->ownerAccountId();

        $staff = $this->staffRoster();
        $this->seedServices(array_column($staff, 'key'));
        $this->seedCustomers();
        $this->loadBusyIntervals();
        $this->loadTakenReferenceCodes();

        $appointments = $this->seedAppointments($staff, $this->bookableServices());
        $paymentIds = $this->seedPayments($appointments);
        $this->backdatePayments($paymentIds);

        $this->command?->info(sprintf(
            'Ensured %d demo services and seeded %d customers, %d appointments and %d payments for "%s".',
            count(self::DEMO_SERVICES),
            count($this->customers),
            count($appointments),
            count($paymentIds),
            self::BUSINESS_SLUG,
        ));
    }

    private function ownerAccountId(): string
    {
        $ownerKeys = DB::table(self::ROLE_ASSIGNMENTS_TABLE)
            ->select('model_id')
            ->where('role_id', SeededStaffRole::OWNER_ID)
            ->where('business_id', $this->business->id)
            ->where('model_type', (new User)->getMorphClass());

        return User::query()->whereIn('id', $ownerKeys)->valueOrFail('uuid');
    }

    /**
     * @return list<array{key: int, accountId: string, shifts: array<int, list<array{opens: string, closes: string}>>}>
     */
    private function staffRoster(): array
    {
        $rules = ScheduleRuleModel::query()
            ->where('business_id', $this->business->id)
            ->where('owner_type', (new StaffMemberModel)->getMorphClass())
            ->orderBy('starts_at')
            ->get();

        return StaffMemberModel::query()
            ->with('account')
            ->where('business_id', $this->business->id)
            ->orderBy('id')
            ->get()
            ->map(fn (StaffMemberModel $member): array => [
                'key' => (int) $member->id,
                'accountId' => $member->account->uuid,
                'shifts' => $this->shiftsOf((int) $member->id, $rules->all()),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<ScheduleRuleModel>  $rules
     * @return array<int, list<array{opens: string, closes: string}>>
     */
    private function shiftsOf(int $staffKey, array $rules): array
    {
        $shifts = [];

        foreach ($rules as $rule) {
            if ((int) $rule->owner_id !== $staffKey) {
                continue;
            }

            $shifts[$rule->weekday][] = ['opens' => (string) $rule->starts_at, 'closes' => (string) $rule->ends_at];
        }

        return $shifts;
    }

    /**
     * @param  list<int>  $staffKeys
     */
    private function seedServices(array $staffKeys): void
    {
        foreach (self::DEMO_SERVICES as $definition) {
            $service = $this->existingService($definition) ?? $this->createService($definition);

            $service->staffMembers()->syncWithoutDetaching($staffKeys);
        }
    }

    /**
     * @param  array{name: string, slug: string, duration: int, buffer: int, price: string, color: ServiceColor}  $definition
     */
    private function existingService(array $definition): ?ServiceModel
    {
        return ServiceModel::query()
            ->where('business_id', $this->business->id)
            ->where(fn ($query) => $query
                ->where('slug', $definition['slug'])
                ->orWhereRaw('lower(name) = ?', [mb_strtolower($definition['name'])]))
            ->first();
    }

    /**
     * @param  array{name: string, slug: string, duration: int, buffer: int, price: string, color: ServiceColor}  $definition
     */
    private function createService(array $definition): ServiceModel
    {
        return ServiceModel::query()->create([
            'uuid' => $this->ids->next(),
            'business_id' => $this->business->id,
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'duration_minutes' => $definition['duration'],
            'buffer_minutes' => $definition['buffer'],
            'price' => $definition['price'],
            'color' => $definition['color'],
            'active' => true,
        ]);
    }

    /**
     * @return list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>
     */
    private function bookableServices(): array
    {
        return ServiceModel::query()
            ->with('staffMembers')
            ->where('business_id', $this->business->id)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceModel $service): array => [
                'key' => (int) $service->id,
                'name' => $service->name,
                'durationMinutes' => $service->duration_minutes,
                'bufferMinutes' => $service->buffer_minutes,
                'price' => Money::fromDecimalString($service->price, $this->currency),
                'weight' => self::SERVICE_WEIGHTS[$service->slug] ?? self::DEFAULT_SERVICE_WEIGHT,
                'staffKeys' => $service->staffMembers->map(fn (StaffMemberModel $member): int => (int) $member->id)->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function seedCustomers(): void
    {
        $firstDay = new DateTimeImmutable(self::HISTORY_STARTS_ON, $this->timezone);
        $spanDays = (int) $firstDay->diff(new DateTimeImmutable(self::LAST_CUSTOMER_JOINS_ON, $this->timezone))->days;
        $lateJoiners = count(self::CUSTOMER_NAMES) - self::FOUNDING_CUSTOMERS;

        foreach (self::CUSTOMER_NAMES as $position => $name) {
            $joinsOn = $firstDay->modify(sprintf('+%d days', $this->joiningOffsetDays($position, $spanDays, $lateJoiners)));

            $this->customers[] = [
                'key' => $this->createCustomer($name, $joinsOn),
                'joinsOn' => $joinsOn->format('Y-m-d'),
            ];
        }
    }

    private function joiningOffsetDays(int $position, int $spanDays, int $lateJoiners): int
    {
        if ($position < self::FOUNDING_CUSTOMERS) {
            return 0;
        }

        return intdiv(($position - self::FOUNDING_CUSTOMERS + 1) * $spanDays, $lateJoiners);
    }

    private function createCustomer(string $name, DateTimeImmutable $joinsOn): int
    {
        $joinedAt = $this->localInstant($joinsOn, self::CUSTOMER_JOINS_AT);

        $customer = (new CustomerModel)->forceFill([
            'uuid' => $this->ids->next(),
            'business_id' => $this->business->id,
            'name' => $name,
            'email' => Str::slug($name, '.').'@'.self::DEMO_EMAIL_DOMAIN,
            'created_at' => $joinedAt,
            'updated_at' => $joinedAt,
        ]);

        $customer->save();

        return (int) $customer->id;
    }

    private function loadBusyIntervals(): void
    {
        AppointmentModel::query()
            ->where('business_id', $this->business->id)
            ->whereNull('cancelled_at')
            ->get(['staff_member_id', 'starts_at', 'ends_at'])
            ->each(fn (AppointmentModel $appointment) => $this->occupy(
                (int) $appointment->staff_member_id,
                $appointment->starts_at,
                $appointment->ends_at,
            ));
    }

    private function loadTakenReferenceCodes(): void
    {
        foreach (AppointmentModel::withTrashed()->pluck('reference_code') as $code) {
            $this->takenReferenceCodes[(string) $code] = true;
        }
    }

    /**
     * @param  list<array{key: int, accountId: string, shifts: array<int, list<array{opens: string, closes: string}>>}>  $staff
     * @param  list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>  $services
     * @return list<array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}>
     */
    private function seedAppointments(array $staff, array $services): array
    {
        $today = $this->now->setTimezone($this->timezone)->format('Y-m-d');
        $lastDay = (new DateTimeImmutable($today, $this->timezone))->modify(sprintf('+%d days', self::UPCOMING_DAYS));
        $appointments = [];

        for ($day = new DateTimeImmutable(self::HISTORY_STARTS_ON, $this->timezone); $day <= $lastDay; $day = $day->modify('+1 day')) {
            foreach ($staff as $member) {
                $eligible = self::servicesFor($member['key'], $services);

                foreach ($member['shifts'][(int) $day->format('N')] ?? [] as $shift) {
                    array_push($appointments, ...$this->fillShift($member, $eligible, $day, $shift, $this->fillPercentFor($day, $today)));
                }
            }
        }

        return $appointments;
    }

    /**
     * @param  list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>  $services
     * @return list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>
     */
    private static function servicesFor(int $staffKey, array $services): array
    {
        return array_values(array_filter(
            $services,
            static fn (array $service): bool => in_array($staffKey, $service['staffKeys'], true),
        ));
    }

    private function fillPercentFor(DateTimeImmutable $day, string $today): int
    {
        [$minimum, $maximum] = $day->format('Y-m-d') > $today
            ? self::UPCOMING_FILL_PERCENT
            : self::MONTHLY_FILL_PERCENT[(int) $day->format('n')] ?? self::DEFAULT_FILL_PERCENT;

        return mt_rand($minimum, $maximum);
    }

    /**
     * @param  array{key: int, accountId: string, shifts: array<int, list<array{opens: string, closes: string}>>}  $member
     * @param  list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>  $services
     * @param  array{opens: string, closes: string}  $shift
     * @return list<array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}>
     */
    private function fillShift(array $member, array $services, DateTimeImmutable $day, array $shift, int $fillPercent): array
    {
        $cursor = $this->localInstant($day, $shift['opens']);
        $closesAt = $this->localInstant($day, $shift['closes']);
        $targetSeconds = intdiv(($closesAt->getTimestamp() - $cursor->getTimestamp()) * $fillPercent, self::PERCENT);
        $bookedSeconds = 0;
        $booked = [];

        while ($bookedSeconds < $targetSeconds && $cursor < $closesAt) {
            if ($this->chance(self::GAP_RATE)) {
                $cursor = $this->afterIdleGap($cursor);

                continue;
            }

            $service = $this->pickServiceFitting($services, $cursor, $closesAt);

            if ($service === null) {
                break;
            }

            $endsAt = self::minutesAfter($cursor, $service['durationMinutes']);
            $blockEndsAt = self::minutesAfter($endsAt, $service['bufferMinutes']);

            if (! $this->isFree($member['key'], $cursor, $blockEndsAt)) {
                $cursor = self::minutesAfter($cursor, self::SLOT_STEP_MINUTES);

                continue;
            }

            $booked[] = $this->book($member, $service, $day, $cursor, $endsAt);
            $bookedSeconds += $service['durationMinutes'] * self::SECONDS_PER_MINUTE;
            $cursor = self::nextSlotFrom($blockEndsAt);
        }

        return $booked;
    }

    /**
     * @param  list<array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}>  $services
     * @return array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}|null
     */
    private function pickServiceFitting(array $services, DateTimeImmutable $startsAt, DateTimeImmutable $closesAt): ?array
    {
        $fitting = array_values(array_filter(
            $services,
            static fn (array $service): bool => self::minutesAfter($startsAt, $service['durationMinutes']) <= $closesAt,
        ));

        if ($fitting === []) {
            return null;
        }

        $roll = mt_rand(1, array_sum(array_column($fitting, 'weight')));

        foreach ($fitting as $service) {
            $roll -= $service['weight'];

            if ($roll <= 0) {
                return $service;
            }
        }

        return $fitting[array_key_last($fitting)];
    }

    /**
     * @param  array{key: int, accountId: string, shifts: array<int, list<array{opens: string, closes: string}>>}  $member
     * @param  array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}  $service
     * @return array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}
     */
    private function book(array $member, array $service, DateTimeImmutable $day, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        $bookedAt = $this->bookingMomentFor($startsAt);
        $cancelledAt = $this->chance(self::CANCELLATION_RATE) ? $this->cancellationMomentFor($bookedAt, $startsAt) : null;

        $appointment = (new AppointmentModel)->forceFill([
            'uuid' => $this->ids->next(),
            'business_id' => $this->business->id,
            'customer_id' => $this->customerFor($day),
            'service_id' => $service['key'],
            'staff_member_id' => $member['key'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notes' => $this->randomNote(),
            'reference_code' => $this->uniqueReferenceCode(),
            'source' => $this->chance(self::PUBLIC_SOURCE_RATE) ? BookingSource::Public : BookingSource::Admin,
            'cancelled_at' => $cancelledAt,
            'cancelled_by' => $cancelledAt === null ? null : $this->randomCanceller(),
            'created_at' => $bookedAt,
            'updated_at' => $cancelledAt ?? $bookedAt,
        ]);

        $appointment->save();

        if ($cancelledAt === null) {
            $this->occupy($member['key'], $startsAt, $endsAt);
        }

        return [
            'key' => (int) $appointment->id,
            'uuid' => $appointment->uuid,
            'accountId' => $member['accountId'],
            'service' => $service,
            'endsAt' => $endsAt,
            'cancelled' => $cancelledAt !== null,
        ];
    }

    private function bookingMomentFor(DateTimeImmutable $startsAt): DateTimeImmutable
    {
        $leadSeconds = mt_rand(0, self::MAXIMUM_BOOKING_LEAD_DAYS) * self::SECONDS_PER_DAY
            + mt_rand(self::MINIMUM_BOOKING_LEAD_HOURS, self::MAXIMUM_BOOKING_LEAD_HOURS) * self::SECONDS_PER_HOUR;
        $bookedAt = $startsAt->modify(sprintf('-%d seconds', $leadSeconds));

        if ($bookedAt < $this->now->modify(sprintf('-%d seconds', self::SECONDS_PER_HOUR))) {
            return $bookedAt;
        }

        return $this->now->modify(sprintf('-%d hours', mt_rand(1, self::MAXIMUM_RECENT_BOOKING_HOURS)));
    }

    private function cancellationMomentFor(DateTimeImmutable $bookedAt, DateTimeImmutable $startsAt): DateTimeImmutable
    {
        $latest = min($startsAt, $this->now);
        $spanSeconds = $latest->getTimestamp() - $bookedAt->getTimestamp();
        $fraction = mt_rand(self::MINIMUM_CANCELLATION_FRACTION, self::MAXIMUM_CANCELLATION_FRACTION);

        return $bookedAt->modify(sprintf('+%d seconds', intdiv($spanSeconds * $fraction, self::PERCENT)));
    }

    private function customerFor(DateTimeImmutable $day): int
    {
        $date = $day->format('Y-m-d');
        $available = array_values(array_filter(
            $this->customers,
            static fn (array $customer): bool => $customer['joinsOn'] <= $date,
        ));
        $newcomer = $this->firstUnvisited($available);

        $customerKey = $newcomer !== null && $this->chance(self::FIRST_VISIT_PRIORITY_RATE)
            ? $newcomer
            : $available[self::loyaltySkewedIndex(count($available))]['key'];

        $this->visitedCustomers[$customerKey] = true;

        return $customerKey;
    }

    /**
     * @param  list<array{key: int, joinsOn: string}>  $customers
     */
    private function firstUnvisited(array $customers): ?int
    {
        foreach ($customers as $customer) {
            if (! isset($this->visitedCustomers[$customer['key']])) {
                return $customer['key'];
            }
        }

        return null;
    }

    private static function loyaltySkewedIndex(int $count): int
    {
        $uniform = mt_rand() / (mt_getrandmax() + 1);

        return (int) floor($count * ($uniform ** self::LOYALTY_SKEW));
    }

    private function randomNote(): ?string
    {
        if (! $this->chance(self::NOTES_RATE)) {
            return null;
        }

        return self::APPOINTMENT_NOTES[mt_rand(0, count(self::APPOINTMENT_NOTES) - 1)];
    }

    private function randomCanceller(): Canceller
    {
        return $this->chance(self::CUSTOMER_CANCELLATION_RATE) ? Canceller::Customer : Canceller::Business;
    }

    private function uniqueReferenceCode(): string
    {
        $lastPosition = strlen(ReferenceCode::ALPHABET) - 1;

        do {
            $code = '';

            for ($character = 0; $character < ReferenceCode::LENGTH; $character++) {
                $code .= ReferenceCode::ALPHABET[mt_rand(0, $lastPosition)];
            }
        } while (isset($this->takenReferenceCodes[$code]));

        $this->takenReferenceCodes[$code] = true;

        return ReferenceCode::fromString($code)->value;
    }

    private function occupy(int $staffKey, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): void
    {
        $this->busyIntervals[$staffKey][] = [$startsAt->getTimestamp(), $endsAt->getTimestamp()];
    }

    private function isFree(int $staffKey, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): bool
    {
        foreach ($this->busyIntervals[$staffKey] ?? [] as [$busyFrom, $busyUntil]) {
            if ($startsAt->getTimestamp() < $busyUntil && $busyFrom < $endsAt->getTimestamp()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}>  $appointments
     * @return list<string>
     */
    private function seedPayments(array $appointments): array
    {
        $methodIds = PaymentMethodModel::query()
            ->whereIn('code', [PaymentMethodCode::Cash->value, PaymentMethodCode::BankTransfer->value])
            ->pluck('uuid', 'code')
            ->all();
        $paymentIds = [];

        foreach ($appointments as $appointment) {
            if ($appointment['cancelled'] || $appointment['endsAt'] > $this->now || ! $this->chance(self::PAYMENT_RATE)) {
                continue;
            }

            $paymentIds[] = $this->collect($appointment, $methodIds);
        }

        return $paymentIds;
    }

    /**
     * @param  array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}  $appointment
     * @param  array<string, string>  $methodIds
     */
    private function collect(array $appointment, array $methodIds): string
    {
        $payment = Payment::open(
            id: $this->ids->next(),
            businessId: $this->business->uuid,
            appointmentId: $appointment['uuid'],
            currency: $this->currency,
            now: $appointment['endsAt'],
        );

        $payment->addItem($this->ids->next(), PaymentItemName::fromString($appointment['service']['name']), $appointment['service']['price']);
        $payment->applyDiscount($this->randomDiscount());

        $this->recordTransactions($payment, $this->collectorFor($appointment), $appointment['endsAt'], $methodIds);

        $this->payments->save($payment);

        return $payment->id;
    }

    private function randomDiscount(): Discount
    {
        if (! $this->chance(self::DISCOUNT_RATE)) {
            return Discount::none();
        }

        if ($this->chance(self::PERCENTAGE_DISCOUNT_RATE)) {
            return Discount::ofPercentage(self::pickFrom(self::PERCENTAGE_DISCOUNTS_BASIS_POINTS));
        }

        return Discount::ofAmount(self::pickFrom(self::FIXED_DISCOUNTS_CENTS));
    }

    /**
     * @param  array{key: int, uuid: string, accountId: string, service: array{key: int, name: string, durationMinutes: int, bufferMinutes: int, price: Money, weight: int, staffKeys: list<int>}, endsAt: DateTimeImmutable, cancelled: bool}  $appointment
     */
    private function collectorFor(array $appointment): string
    {
        if ($appointment['accountId'] !== $this->ownerAccountId && $this->chance(self::STAFF_SELF_COLLECTION_RATE)) {
            return $appointment['accountId'];
        }

        return $this->ownerAccountId;
    }

    /**
     * @param  array<string, string>  $methodIds
     */
    private function recordTransactions(Payment $payment, string $collectorId, DateTimeImmutable $processedAt, array $methodIds): void
    {
        $methodCode = $this->chance(self::CASH_RATE) ? PaymentMethodCode::Cash : PaymentMethodCode::BankTransfer;
        $breakdown = PaymentBreakdown::of($payment->subtotal(), $payment->discount());

        if ($this->chance(self::VOIDED_PAYMENT_RATE)) {
            $this->recordVoidedThenRetaken($payment, $collectorId, $processedAt, $methodCode, $methodIds);

            return;
        }

        $payment->recordTransaction(
            $this->ids->next(),
            $methodIds[$methodCode->value],
            $collectorId,
            $breakdown,
            $this->firstInstalmentOf($payment->total()),
            $processedAt,
        );
    }

    /**
     * @param  array<string, string>  $methodIds
     */
    private function recordVoidedThenRetaken(
        Payment $payment,
        string $collectorId,
        DateTimeImmutable $processedAt,
        PaymentMethodCode $mistakenCode,
        array $methodIds,
    ): void {
        $mistaken = $payment->recordTransaction(
            $this->ids->next(),
            $methodIds[$mistakenCode->value],
            $collectorId,
            PaymentBreakdown::of($payment->subtotal(), $payment->discount()),
            $payment->total(),
            $processedAt,
        );

        $voidedAt = self::minutesAfter($processedAt, mt_rand(self::MINIMUM_VOID_DELAY_MINUTES, self::MAXIMUM_VOID_DELAY_MINUTES));

        $payment->voidTransaction($this->ids->next(), $mistaken->id, $this->ownerAccountId, $voidedAt);

        $payment->recordTransaction(
            $this->ids->next(),
            $methodIds[self::otherMethodThan($mistakenCode)->value],
            $collectorId,
            PaymentBreakdown::none($this->currency),
            $payment->total(),
            self::minutesAfter($voidedAt, mt_rand(self::MINIMUM_RETAKE_DELAY_MINUTES, self::MAXIMUM_RETAKE_DELAY_MINUTES)),
        );
    }

    private static function otherMethodThan(PaymentMethodCode $code): PaymentMethodCode
    {
        return $code === PaymentMethodCode::Cash ? PaymentMethodCode::BankTransfer : PaymentMethodCode::Cash;
    }

    private function firstInstalmentOf(Money $total): Money
    {
        if (! $this->chance(self::PARTIAL_PAYMENT_RATE)) {
            return $total;
        }

        $share = intdiv($total->amount * mt_rand(self::PARTIAL_MINIMUM_PERCENT, self::PARTIAL_MAXIMUM_PERCENT), self::PERCENT);
        $rounded = intdiv($share, self::PARTIAL_ROUNDING_CENTS) * self::PARTIAL_ROUNDING_CENTS;

        if ($rounded < self::PARTIAL_ROUNDING_CENTS || $rounded >= $total->amount) {
            return $total;
        }

        return Money::fromCents($rounded, $total->currency);
    }

    /**
     * @param  list<string>  $paymentIds
     */
    private function backdatePayments(array $paymentIds): void
    {
        foreach (array_chunk($paymentIds, self::BACKDATE_CHUNK_SIZE) as $chunk) {
            $paymentKeys = DB::table('payments')->select('id')->whereIn('uuid', $chunk);

            DB::table('payment_transactions')->whereIn('payment_id', $paymentKeys)->update([
                'created_at' => DB::raw("processed_at at time zone 'UTC'"),
                'updated_at' => DB::raw("processed_at at time zone 'UTC'"),
            ]);

            DB::table('payments')->whereIn('uuid', $chunk)->update([
                'created_at' => DB::raw('(select min(t.created_at) from payment_transactions t where t.payment_id = payments.id)'),
                'updated_at' => DB::raw('(select max(t.created_at) from payment_transactions t where t.payment_id = payments.id)'),
            ]);

            DB::table('payment_items')->whereIn('payment_id', $paymentKeys)->update([
                'created_at' => DB::raw('(select p.created_at from payments p where p.id = payment_items.payment_id)'),
                'updated_at' => DB::raw('(select p.created_at from payments p where p.id = payment_items.payment_id)'),
            ]);
        }
    }

    private function localInstant(DateTimeImmutable $day, string $localTime): DateTimeImmutable
    {
        return (new DateTimeImmutable($day->format('Y-m-d').' '.$localTime, $this->timezone))
            ->setTimezone(new DateTimeZone(self::UTC));
    }

    private function afterIdleGap(DateTimeImmutable $cursor): DateTimeImmutable
    {
        return self::minutesAfter($cursor, mt_rand(1, self::MAXIMUM_GAP_STEPS) * self::SLOT_STEP_MINUTES);
    }

    private static function nextSlotFrom(DateTimeImmutable $instant): DateTimeImmutable
    {
        $step = self::SLOT_STEP_MINUTES * self::SECONDS_PER_MINUTE;

        return $instant->setTimestamp((int) (ceil($instant->getTimestamp() / $step) * $step));
    }

    private static function minutesAfter(DateTimeImmutable $instant, int $minutes): DateTimeImmutable
    {
        return $instant->modify(sprintf('+%d minutes', $minutes));
    }

    /**
     * @param  list<int>  $options
     */
    private static function pickFrom(array $options): int
    {
        return $options[mt_rand(0, count($options) - 1)];
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, self::PERCENT) <= $percent;
    }
}
