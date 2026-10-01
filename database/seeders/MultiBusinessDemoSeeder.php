<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Staff\Application\Dtos\GenerateStaffBookingLinkInput;
use App\Domains\Staff\Application\UseCases\GenerateStaffBookingLink;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Models\User;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MultiBusinessDemoSeeder extends Seeder
{
    private const SOURCE_BUSINESS_SLUG = 'cop';

    private const BUSINESS_NAME = 'Barbería Norte';

    private const BUSINESS_SLUG = 'norte';

    private const OWNER_EMAIL = 'owner.norte@demo.mizita.test';

    private const OWNER_NAME = 'Norte Owner';

    private const OWNER_PASSWORD = 'password';

    private const STAFF_EMAIL = 'jose.pablo@gmail.com';

    private const DEMO_EMAIL_DOMAIN = 'demo.mizita.test';

    private const FAKE_STRIPE_CUSTOMER_ID = 'cus_demo_norte';

    private const FAKE_STRIPE_SUBSCRIPTION_ID = 'sub_demo_norte';

    private const SUBSCRIPTION_PERIOD = '+1 year';

    private const OPENS_AT = '10:00';

    private const CLOSES_AT = '19:00';

    private const UTC = 'UTC';

    /**
     * @var list<Weekday>
     */
    private const OPENING_DAYS = [
        Weekday::Monday,
        Weekday::Tuesday,
        Weekday::Wednesday,
        Weekday::Thursday,
        Weekday::Friday,
        Weekday::Saturday,
    ];

    /**
     * @var list<array{name: string, slug: string, duration: int, buffer: int, price: string, color: ServiceColor}>
     */
    private const DEMO_SERVICES = [
        ['name' => 'Corte clásico', 'slug' => 'corte-clasico', 'duration' => 30, 'buffer' => 5, 'price' => '150.00', 'color' => ServiceColor::Amber],
        ['name' => 'Barba', 'slug' => 'barba', 'duration' => 20, 'buffer' => 5, 'price' => '100.00', 'color' => ServiceColor::Teal],
        ['name' => 'Corte y barba', 'slug' => 'corte-y-barba', 'duration' => 50, 'buffer' => 10, 'price' => '230.00', 'color' => ServiceColor::Blue],
    ];

    /**
     * @var list<string>
     */
    private const CUSTOMER_NAMES = [
        'Martín Cárdenas',
        'Lorena Ibarra',
        'Samuel Garza',
        'Patricia Villegas',
    ];

    /**
     * @var list<array{daysAfterMonday: int, startsAt: string, service: string, customer: int}>
     */
    private const STAFF_APPOINTMENTS = [
        ['daysAfterMonday' => 0, 'startsAt' => '11:00', 'service' => 'corte-clasico', 'customer' => 0],
        ['daysAfterMonday' => 2, 'startsAt' => '16:30', 'service' => 'corte-y-barba', 'customer' => 1],
        ['daysAfterMonday' => 4, 'startsAt' => '10:30', 'service' => 'barba', 'customer' => 2],
        ['daysAfterMonday' => 5, 'startsAt' => '12:00', 'service' => 'corte-clasico', 'customer' => 3],
        ['daysAfterMonday' => 8, 'startsAt' => '13:00', 'service' => 'corte-y-barba', 'customer' => 0],
        ['daysAfterMonday' => 10, 'startsAt' => '17:00', 'service' => 'barba', 'customer' => 1],
    ];

    private string $businessId;

    private int $businessKey;

    private DateTimeZone $timezone;

    public function __construct(
        private readonly OnboardBusiness $onboardBusiness,
        private readonly BusinessRepository $businesses,
        private readonly PlanCatalog $plans,
        private readonly SubscriptionRepository $subscriptions,
        private readonly ScheduleRuleRepository $scheduleRules,
        private readonly StaffMemberRepository $staffMembers,
        private readonly StaffProfileRepository $staffProfiles,
        private readonly TeamRoster $roster,
        private readonly BusinessTeamKey $businessKeys,
        private readonly ReferenceCodeGenerator $referenceCodes,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function run(): void
    {
        $staffAccount = User::query()->where('email', self::STAFF_EMAIL)->first();

        if ($staffAccount === null) {
            $this->command?->error(sprintf('Account "%s" not found; create it before running this seeder.', self::STAFF_EMAIL));

            return;
        }

        if ($this->businesses->existsBySlug(self::BUSINESS_SLUG)) {
            $this->ensureExistingBusinessMembership($staffAccount);

            return;
        }

        $this->seedFreshBusiness($staffAccount);
    }

    private function ensureExistingBusinessMembership(User $staffAccount): void
    {
        $businessId = $this->businesses->findBySlug(self::BUSINESS_SLUG)->id;

        if (in_array($staffAccount->uuid, $this->roster->accountIdsOnTeam($businessId), true)) {
            $this->command?->info(sprintf('"%s" already exists and %s is on its team; nothing to do.', self::BUSINESS_SLUG, self::STAFF_EMAIL));

            return;
        }

        DB::transaction(fn () => $this->registerStaffMember($businessId, $staffAccount->uuid));

        $this->command?->info(sprintf('"%s" already existed; added %s to its team.', self::BUSINESS_SLUG, self::STAFF_EMAIL));
    }

    private function seedFreshBusiness(User $staffAccount): void
    {
        $sourceBusiness = $this->sourceBusiness();
        $completePlan = $this->completePlan();

        if ($sourceBusiness === null || $completePlan === null) {
            return;
        }

        DB::transaction(fn () => $this->seedBusiness($sourceBusiness, $completePlan, $staffAccount));

        $this->command?->info(sprintf(
            'Seeded "%s" (%s) owned by %s / %s, with %s on its team, %d services, %d customers and %d appointments.',
            self::BUSINESS_NAME,
            self::BUSINESS_SLUG,
            self::OWNER_EMAIL,
            self::OWNER_PASSWORD,
            self::STAFF_EMAIL,
            count(self::DEMO_SERVICES),
            count(self::CUSTOMER_NAMES),
            count(self::STAFF_APPOINTMENTS),
        ));
    }

    private function sourceBusiness(): ?Business
    {
        if (! $this->businesses->existsBySlug(self::SOURCE_BUSINESS_SLUG)) {
            $this->command?->error(sprintf('Business "%s" not found; it supplies the timezone, currency and industry.', self::SOURCE_BUSINESS_SLUG));

            return null;
        }

        return $this->businesses->findBySlug(self::SOURCE_BUSINESS_SLUG);
    }

    private function completePlan(): ?PlanOffer
    {
        foreach ($this->plans->all() as $offer) {
            if ($offer->key === Plan::Complete) {
                return $offer;
            }
        }

        $this->command?->error('The Complete plan is not seeded; run PlanSeeder first.');

        return null;
    }

    private function seedBusiness(Business $sourceBusiness, PlanOffer $completePlan, User $staffAccount): void
    {
        $owner = $this->ownerAccount();

        $this->businessId = $this->onboard($owner, $sourceBusiness);
        $this->businessKey = $this->businessKeys->teamKeyFor($this->businessId);
        $this->timezone = new DateTimeZone($sourceBusiness->timezone());

        $this->subscribeToCompletePlan($completePlan);
        $this->setBusinessHours();

        $staffMemberId = $this->registerStaffMember($this->businessId, $staffAccount->uuid);
        $staffKey = $this->staffKeyOf($staffMemberId);

        $services = $this->seedServices([$staffKey, ...$this->otherStaffKeysThan($staffKey)]);
        $this->generateBookingLink($staffMemberId);

        $this->seedAppointments($staffKey, $services, $this->seedCustomers());
    }

    private function ownerAccount(): User
    {
        $owner = User::query()->firstOrNew(['email' => self::OWNER_EMAIL]);

        $owner->forceFill([
            'uuid' => $owner->uuid ?? $this->ids->next(),
            'name' => self::OWNER_NAME,
            'password' => self::OWNER_PASSWORD,
            'email_verified_at' => $owner->email_verified_at ?? $this->clock->now(),
            'must_change_password' => false,
        ])->save();

        return $owner;
    }

    private function onboard(User $owner, Business $sourceBusiness): string
    {
        $onboarded = $this->onboardBusiness->handle(new OnboardBusinessInput(
            ownerAccountId: $owner->uuid,
            name: self::BUSINESS_NAME,
            timezone: $sourceBusiness->timezone(),
            industryId: $sourceBusiness->industryId(),
        ))->value();

        $business = $this->businesses->findById($onboarded->id);
        $business->changeSlug(Slug::fromString(self::BUSINESS_SLUG));
        $business->changeCurrency(CurrencyCode::fromString($sourceBusiness->currency()));

        $this->businesses->save($business);

        return $business->id;
    }

    private function subscribeToCompletePlan(PlanOffer $completePlan): void
    {
        $now = $this->clock->now();

        $this->subscriptions->save(Subscription::restore(
            id: $this->ids->next(),
            businessId: $this->businessId,
            billingCustomerId: self::FAKE_STRIPE_CUSTOMER_ID,
            planId: $completePlan->id,
            plan: $completePlan->key,
            status: SubscriptionStatus::Active,
            billingSubscriptionId: self::FAKE_STRIPE_SUBSCRIPTION_ID,
            startedAt: $now,
            currentPeriodEndsAt: $now->modify(self::SUBSCRIPTION_PERIOD),
            canceledAt: null,
            paymentFailedAt: null,
            createdAt: $now,
        ));
    }

    private function setBusinessHours(): void
    {
        $now = $this->clock->now();
        $opensAt = TimeOfDay::fromString(self::OPENS_AT);
        $closesAt = TimeOfDay::fromString(self::CLOSES_AT);

        $rules = array_map(
            fn (Weekday $weekday): ScheduleRule => ScheduleRule::create(
                id: $this->ids->next(),
                businessId: $this->businessId,
                ownerType: ScheduleOwnerType::Business,
                ownerId: $this->businessId,
                weekday: $weekday,
                startsAt: $opensAt,
                endsAt: $closesAt,
                now: $now,
            ),
            self::OPENING_DAYS,
        );

        $this->scheduleRules->replaceForOwner($this->businessId, ScheduleOwnerType::Business, $this->businessId, $rules);
    }

    private function registerStaffMember(string $businessId, string $accountId): string
    {
        $now = $this->clock->now();

        $member = StaffMember::register(
            id: $this->ids->next(),
            businessId: $businessId,
            accountId: $accountId,
            now: $now,
            role: StaffRole::Member,
        );

        $this->staffMembers->save($member);

        $this->staffProfiles->save(StaffProfile::create(
            id: $this->ids->next(),
            businessId: $businessId,
            staffMemberId: $member->id,
            now: $now,
        ));

        return $member->id;
    }

    private function staffKeyOf(string $staffMemberId): int
    {
        return (int) StaffMemberModel::query()->where('uuid', $staffMemberId)->valueOrFail('id');
    }

    /**
     * @return list<int>
     */
    private function otherStaffKeysThan(int $staffKey): array
    {
        return StaffMemberModel::query()
            ->where('business_id', $this->businessKey)
            ->whereKeyNot($staffKey)
            ->pluck('id')
            ->map(static fn (mixed $key): int => (int) $key)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $staffKeys
     * @return array<string, array{key: int, duration: int}>
     */
    private function seedServices(array $staffKeys): array
    {
        $services = [];

        foreach (self::DEMO_SERVICES as $definition) {
            $service = $this->createService($definition);
            $service->staffMembers()->syncWithoutDetaching($staffKeys);

            $services[$definition['slug']] = ['key' => (int) $service->id, 'duration' => $definition['duration']];
        }

        return $services;
    }

    /**
     * @param  array{name: string, slug: string, duration: int, buffer: int, price: string, color: ServiceColor}  $definition
     */
    private function createService(array $definition): ServiceModel
    {
        return ServiceModel::query()->create([
            'uuid' => $this->ids->next(),
            'business_id' => $this->businessKey,
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'duration_minutes' => $definition['duration'],
            'buffer_minutes' => $definition['buffer'],
            'price' => $definition['price'],
            'color' => $definition['color'],
            'active' => true,
        ]);
    }

    private function generateBookingLink(string $staffMemberId): void
    {
        $generateBookingLink = $this->container->make(GenerateStaffBookingLink::class, [
            'business' => $this->tenantContext(),
        ]);

        $generateBookingLink->handle(new GenerateStaffBookingLinkInput($staffMemberId))->value();
    }

    private function tenantContext(): BusinessContext
    {
        return new readonly class($this->businessId) implements BusinessContext
        {
            public function __construct(
                private string $businessId,
            ) {}

            public function currentBusinessId(): string
            {
                return $this->businessId;
            }
        };
    }

    /**
     * @return list<int>
     */
    private function seedCustomers(): array
    {
        return array_map(
            fn (string $name): int => $this->createCustomer($name),
            self::CUSTOMER_NAMES,
        );
    }

    private function createCustomer(string $name): int
    {
        $customer = (new CustomerModel)->forceFill([
            'uuid' => $this->ids->next(),
            'business_id' => $this->businessKey,
            'name' => $name,
            'email' => Str::slug($name, '.').'@'.self::DEMO_EMAIL_DOMAIN,
        ]);

        $customer->save();

        return (int) $customer->id;
    }

    /**
     * @param  array<string, array{key: int, duration: int}>  $services
     * @param  list<int>  $customerKeys
     */
    private function seedAppointments(int $staffKey, array $services, array $customerKeys): void
    {
        $monday = $this->mondayOfThisWeek();

        foreach (self::STAFF_APPOINTMENTS as $appointment) {
            $service = $services[$appointment['service']];
            $startsAt = $this->localInstant($monday->modify(sprintf('+%d days', $appointment['daysAfterMonday'])), $appointment['startsAt']);

            (new AppointmentModel)->forceFill([
                'uuid' => $this->ids->next(),
                'business_id' => $this->businessKey,
                'customer_id' => $customerKeys[$appointment['customer']],
                'service_id' => $service['key'],
                'staff_member_id' => $staffKey,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->modify(sprintf('+%d minutes', $service['duration'])),
                'reference_code' => $this->referenceCodes->next()->value,
                'source' => BookingSource::Admin,
            ])->save();
        }
    }

    private function mondayOfThisWeek(): DateTimeImmutable
    {
        $today = new DateTimeImmutable($this->clock->now()->setTimezone($this->timezone)->format('Y-m-d'), $this->timezone);

        return $today->modify(sprintf('-%d days', (int) $today->format('N') - Weekday::Monday->value));
    }

    private function localInstant(DateTimeImmutable $day, string $localTime): DateTimeImmutable
    {
        return (new DateTimeImmutable($day->format('Y-m-d').' '.$localTime, $this->timezone))
            ->setTimezone(new DateTimeZone(self::UTC));
    }
}
