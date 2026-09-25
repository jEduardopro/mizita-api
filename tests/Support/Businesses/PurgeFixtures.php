<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Models\BookingPolicyModel;
use App\Domains\Businesses\Application\Dtos\CloseBusinessInput;
use App\Domains\Businesses\Application\Dtos\PurgeClosedBusinessInput;
use App\Domains\Businesses\Application\UseCases\CloseBusiness;
use App\Domains\Businesses\Application\UseCases\PurgeClosedBusiness;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Links\Infrastructure\Eloquent\Models\LinkModel;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Payments\Infrastructure\Eloquent\Models\BusinessPaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentItemModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentTransactionModel;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Models\User;
use App\Shared\Application\UseCaseResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class PurgeFixtures
{
    private const string APPOINTMENT_STARTS_AT = '2026-10-05T15:00:00+00:00';

    private const string APPOINTMENT_ENDS_AT = '2026-10-05T15:30:00+00:00';

    private const string PAYMENT_PROCESSED_AT = '2026-10-05T15:35:00+00:00';

    private const int PAYMENT_TOTAL_CENTS = 45000;

    private const string USER_MORPH_ALIAS = 'user';

    private function __construct(
        public BusinessModel $business,
        public User $owner,
        public User $staffAccount,
        public StaffMemberModel $staffMember,
        public Media $logo,
        public Media $serviceImage,
    ) {}

    public static function seedTenant(PaymentMethodModel $paymentMethod): self
    {
        $business = BusinessModel::factory()->create();
        $owner = User::factory()->create();

        StaffMemberModel::factory()->owner()->create([
            'business_id' => $business->id,
            'account_id' => $owner->id,
        ]);

        $staffAccount = User::factory()->create();
        $staffMember = self::enrol($staffAccount, $business);
        $staffProfile = StaffProfileModel::factory()->create(['staff_member_id' => $staffMember->id]);
        $service = ServiceModel::factory()->create(['business_id' => $business->id]);
        $customer = CustomerModel::factory()->create(['business_id' => $business->id]);

        DB::table('service_staff')->insert([
            'service_id' => $service->id,
            'staff_member_id' => $staffMember->id,
        ]);

        self::seedLedger($business, $customer, $service, $staffMember, $paymentMethod);
        self::seedSchedule($business, $staffMember);
        self::seedContactDetails($business, $staffMember, $staffProfile, $customer);
        self::seedDirectPermission($business, $staffAccount);

        BookingPageModel::factory()->create(['business_id' => $business->id]);
        BookingPolicyModel::factory()->create(['business_id' => $business->id]);

        return new self(
            business: $business,
            owner: $owner,
            staffAccount: $staffAccount,
            staffMember: $staffMember,
            logo: $business->addMedia(UploadedFile::fake()->image('logo.png'))
                ->toMediaCollection(BusinessModel::LOGO_COLLECTION),
            serviceImage: $service->addMedia(UploadedFile::fake()->image('service.png'))
                ->toMediaCollection(ServiceModel::IMAGE_COLLECTION),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function enrol(User $account, BusinessModel $business, array $attributes = []): StaffMemberModel
    {
        return StaffMemberModel::factory()->create([
            ...$attributes,
            'business_id' => $business->id,
            'account_id' => $account->id,
        ]);
    }

    public function closeByOwner(): void
    {
        app(CloseBusiness::class)
            ->handle(new CloseBusinessInput(businessId: $this->business->uuid, ownerAccountId: $this->owner->uuid))
            ->value();

        $this->owner->delete();
    }

    /**
     * @return UseCaseResponse<null>
     */
    public function purge(): UseCaseResponse
    {
        return app(PurgeClosedBusiness::class)->handle(new PurgeClosedBusinessInput(businessId: $this->business->uuid));
    }

    public function storedBusiness(): BusinessModel
    {
        return BusinessModel::withTrashed()->whereKey($this->business->id)->sole();
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, list<string>>
     */
    public static function rowsOf(array $tables): array
    {
        $rows = [];

        foreach ($tables as $table) {
            $encoded = DB::table($table)
                ->get()
                ->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR))
                ->all();

            sort($encoded);

            $rows[$table] = $encoded;
        }

        return $rows;
    }

    /**
     * @param  array<string, list<string>>  $before
     * @param  array<string, list<string>>  $after
     * @return array<string, list<string>>
     */
    public static function rowsOnlyIn(array $before, array $after): array
    {
        $difference = [];

        foreach ($before as $table => $rows) {
            $difference[$table] = array_values(array_diff($rows, $after[$table] ?? []));
        }

        return $difference;
    }

    private static function seedLedger(
        BusinessModel $business,
        CustomerModel $customer,
        ServiceModel $service,
        StaffMemberModel $staffMember,
        PaymentMethodModel $paymentMethod,
    ): void {
        $appointment = AppointmentModel::factory()->create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'staff_member_id' => $staffMember->id,
            'starts_at' => self::APPOINTMENT_STARTS_AT,
            'ends_at' => self::APPOINTMENT_ENDS_AT,
        ]);

        $payment = PaymentModel::factory()->create([
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'total_cents' => self::PAYMENT_TOTAL_CENTS,
        ]);

        PaymentItemModel::factory()->create([
            'payment_id' => $payment->id,
            'amount_cents' => self::PAYMENT_TOTAL_CENTS,
        ]);

        PaymentTransactionModel::factory()->create([
            'payment_id' => $payment->id,
            'payment_method_id' => $paymentMethod->id,
            'total_cents' => self::PAYMENT_TOTAL_CENTS,
            'processed_at' => self::PAYMENT_PROCESSED_AT,
        ]);

        BusinessPaymentMethodModel::factory()->create([
            'business_id' => $business->id,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    private static function seedSchedule(BusinessModel $business, StaffMemberModel $staffMember): void
    {
        ScheduleRuleModel::factory()->create([
            'business_id' => $business->id,
            'owner_type' => ScheduleOwnerType::Business->value,
            'owner_id' => $business->id,
            'weekday' => Weekday::Monday->value,
        ]);

        ScheduleRuleModel::factory()->create([
            'business_id' => $business->id,
            'owner_type' => ScheduleOwnerType::StaffMember->value,
            'owner_id' => $staffMember->id,
            'weekday' => Weekday::Tuesday->value,
        ]);
    }

    private static function seedContactDetails(
        BusinessModel $business,
        StaffMemberModel $staffMember,
        StaffProfileModel $staffProfile,
        CustomerModel $customer,
    ): void {
        PhoneModel::factory()->ownedBy(PhoneOwnerType::Business, $business->id)->create();
        PhoneModel::factory()->ownedBy(PhoneOwnerType::StaffMember, $staffMember->id)->create();
        PhoneModel::factory()->ownedBy(PhoneOwnerType::StaffProfile, $staffProfile->id)->create();
        PhoneModel::factory()->ownedBy(PhoneOwnerType::Customer, $customer->id)->create();

        self::address(AddressOwnerType::Business, $business->id);
        self::address(AddressOwnerType::StaffMember, $staffMember->id);
        self::address(AddressOwnerType::Customer, $customer->id);

        LinkModel::factory()->create([
            'linkable_type' => LinkOwnerType::Business->value,
            'linkable_id' => $business->id,
        ]);

        LinkModel::factory()->create([
            'linkable_type' => LinkOwnerType::StaffMember->value,
            'linkable_id' => $staffMember->id,
        ]);
    }

    public static function address(AddressOwnerType $ownerType, int $ownerKey): AddressModel
    {
        return AddressModel::factory()->create([
            'addressable_type' => $ownerType->value,
            'addressable_id' => $ownerKey,
        ]);
    }

    private static function seedDirectPermission(BusinessModel $business, User $account): void
    {
        DB::table('model_has_permissions')->insert([
            'permission_id' => DB::table('permissions')->orderBy('id')->value('id'),
            'model_type' => self::USER_MORPH_ALIAS,
            'model_id' => $account->id,
            'business_id' => $business->id,
        ]);
    }
}
