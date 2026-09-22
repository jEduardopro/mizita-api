<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentCustomerData;
use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\AppointmentServiceData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->journal = new AppointmentJournal;

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            phone: AppointmentFixtures::customerPhoneSnapshot(),
        ));
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->presenter = new AppointmentPresenter($this->services, $this->customers, $this->staff);

    $this->pageOf = fn (array $appointments, int $total, Pagination $pagination): Paginated => $this->presenter
        ->describePage(FakeBusinessContext::BUSINESS_ID, Paginated::of($appointments, $total, $pagination));
});

describe('describing one appointment', function () {
    it('carries what the service costs and how long it blocks', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->service)->toBeInstanceOf(AppointmentServiceData::class)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($data->service->bufferMinutes)->toBe(AppointmentFixtures::SERVICE_BUFFER_MINUTES)
            ->and($data->service->price)->toBe(AppointmentFixtures::SERVICE_PRICE)
            ->and($data->service->price)->toBeString();
    });

    it('carries the phone the customer can be reached on', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->customer)->toBeInstanceOf(AppointmentCustomerData::class)
            ->and($data->customer->phone?->countryCode)->toBe(AppointmentFixtures::CUSTOMER_PHONE_COUNTRY_CODE)
            ->and($data->customer->phone?->nationalNumber)->toBe(AppointmentFixtures::CUSTOMER_PHONE_NATIONAL_NUMBER);
    });

    it('carries no phone for a customer who left none', function () {
        $this->customers->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        $data = $this->presenter->describe(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::appointment(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID),
        );

        expect($data->customer->phone)->toBeNull();
    });
});

describe('describing a page', function () {
    beforeEach(function () {
        $this->first = AppointmentFixtures::appointment();
        $this->second = AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            startsAt: '2026-04-10T09:00:00+00:00',
            endsAt: '2026-04-10T10:00:00+00:00',
        );
        $this->third = AppointmentFixtures::appointment(
            id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
            startsAt: '2026-05-10T09:00:00+00:00',
            endsAt: '2026-05-10T10:00:00+00:00',
        );
    });

    it('keeps the order the repository handed it', function () {
        $page = ($this->pageOf)([$this->third, $this->first, $this->second], 3, Pagination::of(1, 20));

        expect(array_map(static fn (AppointmentData $data): string => $data->id, $page->items))->toBe([
            AppointmentFixtures::THIRD_APPOINTMENT_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::SECOND_APPOINTMENT_ID,
        ]);
    });

    it('keeps the total and the pagination of the page it was given', function () {
        $page = ($this->pageOf)([$this->first], 42, Pagination::of(3, 5));

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(5)
            ->and($page->lastPage())->toBe(9);
    });

    it('asks each neighbour once for the whole page, never once per row', function () {
        ($this->pageOf)([$this->first, $this->second, $this->third], 3, Pagination::of(1, 20));

        expect($this->customers->batchReads)->toHaveCount(1)
            ->and($this->customers->reads)->toBe([])
            ->and($this->services->batchReads)->toHaveCount(1)
            ->and($this->services->reads)->toBe([])
            ->and($this->staff->batchReads)->toHaveCount(1)
            ->and($this->staff->reads)->toBe([]);
    });

    it('asks each neighbour for one id when three rows share the same neighbour', function () {
        ($this->pageOf)([$this->first, $this->second, $this->third], 3, Pagination::of(1, 20));

        expect($this->customers->batchReads[0]['customerIds'])->toBe([AppointmentFixtures::CUSTOMER_ID])
            ->and($this->services->batchReads[0]['serviceIds'])->toBe([AppointmentFixtures::SERVICE_ID])
            ->and($this->staff->batchReads[0]['staffMemberIds'])->toBe([AppointmentFixtures::STAFF_ID]);
    });

    it('asks nobody anything for an empty page', function () {
        $page = ($this->pageOf)([], 0, Pagination::of(1, 20));

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(0)
            ->and($this->journal->entries)->toBe([]);
    });

    it('reads the neighbours of the business it was asked about', function () {
        ($this->pageOf)([$this->first], 1, Pagination::of(1, 20));

        expect($this->customers->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->services->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->staff->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('refuses the page when a neighbour of one row is missing', function (callable $appointment, string $exception) {
        expect(fn () => ($this->pageOf)([$appointment()], 1, Pagination::of(1, 20)))->toThrow($exception);
    })->with([
        'an unknown customer' => [
            fn () => AppointmentFixtures::appointment(customerId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentCustomerNotFound::class,
        ],
        'an unknown service' => [
            fn () => AppointmentFixtures::appointment(serviceId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentServiceNotFound::class,
        ],
        'an unknown staff member' => [
            fn () => AppointmentFixtures::appointment(staffMemberId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentStaffNotFound::class,
        ],
    ]);

    it('shows nothing of a neighbour filed under another business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        expect(fn () => ($this->pageOf)(
            [AppointmentFixtures::appointment(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID)],
            1,
            Pagination::of(1, 20),
        ))->toThrow(AppointmentCustomerNotFound::class);
    });
});
