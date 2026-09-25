<?php

declare(strict_types=1);

use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Businesses\Infrastructure\Purge\DatabaseTenantDataEraser;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Models\User;
use App\Shared\Contracts\Clock;
use App\Shared\ValueObjects\DomainFailureKind;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Businesses\PurgeFixtures;
use Tests\Support\FakeClock;

uses(RefreshDatabase::class);

const PURGE_CLOSED_AT = '2026-03-29T00:30:00+00:00';

const PURGE_COLLIDING_OWNER_KEY = 900001;

beforeEach(function () {
    $this->seed(AuthorizationSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->disk = Storage::fake(config('media-library.disk_name'));
    $this->clock = new FakeClock(new DateTimeImmutable(PURGE_CLOSED_AT));
    $this->app->instance(Clock::class, $this->clock);

    $paymentMethod = PaymentMethodModel::factory()->create();

    $this->kept = PurgeFixtures::seedTenant($paymentMethod);
    $this->keptRows = PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables());

    $this->closing = PurgeFixtures::seedTenant($paymentMethod);
    PurgeFixtures::enrol($this->kept->staffAccount, $this->closing->business);
    $this->rowsBeforePurge = PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables());
});

it('seeds the closing business into every covered table, so the erasure never passes by vacuity', function () {
    $emptyTables = array_keys(array_filter(
        PurgeFixtures::rowsOnlyIn($this->rowsBeforePurge, $this->keptRows),
        static fn (array $rows): bool => $rows === [],
    ));

    expect($emptyTables)->toBe([]);
});

describe('a business closed more than thirty days ago', function () {
    beforeEach(function () {
        $this->closing->closeByOwner();
        $this->clock->advance('P31D');

        $this->artisan('businesses:purge-closed')->assertSuccessful();

        $this->rowsAfterPurge = PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables());
    });

    it('erases every row the closed business owned', function () {
        expect(PurgeFixtures::rowsOnlyIn($this->rowsAfterPurge, $this->keptRows))
            ->toBe(array_fill_keys(DatabaseTenantDataEraser::coveredTables(), []));
    });

    it('leaves every row of the other business untouched', function () {
        expect(PurgeFixtures::rowsOnlyIn($this->keptRows, $this->rowsAfterPurge))
            ->toBe(array_fill_keys(DatabaseTenantDataEraser::coveredTables(), []));
    });

    it('keeps the other business membership of an account that also worked at the closed business', function () {
        $account = $this->kept->staffAccount;

        expect(StaffMemberModel::query()->where('account_id', $account->id)->pluck('business_id')->all())
            ->toBe([$this->kept->business->id])
            ->and(DB::table('model_has_roles')->where('model_id', $account->id)->pluck('business_id')->all())
            ->toBe([$this->kept->business->id]);
    });

    it('deletes the media files of the closed business from the disk', function () {
        $this->disk->assertMissing($this->closing->logo->getPathRelativeToRoot());
        $this->disk->assertMissing($this->closing->serviceImage->getPathRelativeToRoot());
    });

    it('keeps the media files of the other business on the disk', function () {
        $this->disk->assertExists($this->kept->logo->getPathRelativeToRoot());
        $this->disk->assertExists($this->kept->serviceImage->getPathRelativeToRoot());
    });

    it('keeps the closed business row soft deleted', function () {
        $business = $this->closing->storedBusiness();

        expect($business->trashed())->toBeTrue()
            ->and(DateTimeImmutable::createFromInterface($business->closed_at))
            ->toEqual(new DateTimeImmutable(PURGE_CLOSED_AT));
    });

    it('stamps the purge instant on the closed business', function () {
        expect(DateTimeImmutable::createFromInterface($this->closing->storedBusiness()->purged_at))
            ->toEqual($this->clock->now());
    });

    it('keeps the owner account row soft deleted', function () {
        expect(User::withTrashed()->whereKey($this->closing->owner->id)->sole()->trashed())->toBeTrue();
    });

    it('keeps the accounts of the closed business staff alive', function () {
        expect(User::query()->whereKey($this->closing->staffAccount->id)->exists())->toBeTrue();
    });

    it('never touches the business that was not closed', function () {
        $business = $this->kept->storedBusiness();

        expect($business->trashed())->toBeFalse()
            ->and($business->closed_at)->toBeNull()
            ->and($business->purged_at)->toBeNull();
    });

    describe('purged a second time', function () {
        beforeEach(function () {
            $this->purgedAt = $this->clock->now();
            $this->clock->advance('P1D');

            $this->secondPurge = $this->closing->purge();
        });

        it('succeeds', function () {
            expect($this->secondPurge->succeeded())->toBeTrue();
        });

        it('erases nothing more', function () {
            expect(PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables()))->toBe($this->rowsAfterPurge);
        });

        it('keeps the original purge instant', function () {
            expect(DateTimeImmutable::createFromInterface($this->closing->storedBusiness()->purged_at))
                ->toEqual($this->purgedAt);
        });
    });
});

describe('a business closed less than thirty days ago', function () {
    beforeEach(function () {
        $this->closing->closeByOwner();
        $this->clock->advance('P29DT23H59M59S');
    });

    it('refuses to purge it as not yet due', function () {
        $response = $this->closing->purge();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_due_for_purge')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('erases nothing when the purge is refused', function () {
        $this->closing->purge();

        expect(PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables()))->toBe($this->rowsBeforePurge)
            ->and($this->closing->storedBusiness()->purged_at)->toBeNull();
    });

    it('keeps its media files on the disk when the purge is refused', function () {
        $this->closing->purge();

        $this->disk->assertExists($this->closing->logo->getPathRelativeToRoot());
    });

    it('is left out of the daily purge command', function () {
        $this->artisan('businesses:purge-closed')->assertSuccessful();

        expect(PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables()))->toBe($this->rowsBeforePurge)
            ->and($this->closing->storedBusiness()->purged_at)->toBeNull();
    });
});

it('purges a business closed exactly thirty days ago', function () {
    $this->closing->closeByOwner();
    $this->clock->advance('P30D');

    expect($this->closing->purge()->succeeded())->toBeTrue()
        ->and(DateTimeImmutable::createFromInterface($this->closing->storedBusiness()->purged_at))
        ->toEqual($this->clock->now());
});

describe('a business that was never closed', function () {
    beforeEach(function () {
        $this->clock->advance('P365D');

        $this->response = $this->kept->purge();
    });

    it('refuses to purge it as not closed', function () {
        expect($this->response->failed())->toBeTrue()
            ->and($this->response->error()->code)->toBe('business_not_closed');
    });

    it('erases nothing when the purge is refused', function () {
        expect(PurgeFixtures::rowsOf(DatabaseTenantDataEraser::coveredTables()))->toBe($this->rowsBeforePurge);
    });
});

it('keeps a polymorphic row of the other business whose owner key collides with a purged owner of another type', function () {
    $keptCustomer = CustomerModel::factory()->create([
        'id' => PURGE_COLLIDING_OWNER_KEY,
        'business_id' => $this->kept->business->id,
    ]);
    $keptPhone = PhoneModel::factory()->ownedBy(PhoneOwnerType::Customer, $keptCustomer->id)->create();
    $keptAddress = PurgeFixtures::address(AddressOwnerType::Customer, $keptCustomer->id);

    $collidingMember = PurgeFixtures::enrol(User::factory()->create(), $this->closing->business, [
        'id' => PURGE_COLLIDING_OWNER_KEY,
    ]);
    PhoneModel::factory()->ownedBy(PhoneOwnerType::StaffMember, $collidingMember->id)->create();
    PurgeFixtures::address(AddressOwnerType::StaffMember, $collidingMember->id);

    $this->closing->closeByOwner();
    $this->clock->advance('P31D');
    $this->closing->purge()->value();

    expect(PhoneModel::query()->whereKey($keptPhone->id)->exists())->toBeTrue()
        ->and(AddressModel::query()->whereKey($keptAddress->id)->exists())->toBeTrue();
});
