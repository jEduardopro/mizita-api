<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Application\UseCases\BookAppointmentAsGuest;
use App\Domains\Appointments\Application\UseCases\CancelGuestBooking;
use App\Domains\Appointments\Application\UseCases\RescheduleGuestBooking;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Contracts\ManageTokenFactory;
use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Exceptions\AppointmentSlotNotBookable;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\GuestAddress;
use App\Domains\Appointments\ValueObjects\GuestContact;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AppointmentsGuestBookingDesk;
use App\Domains\PublicCatalog\Infrastructure\Mappers\GuestBookingMapper;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($journal);

    $services = (new FakeServiceCatalog($journal))
        ->add(PublicCatalogFixtures::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($journal))
        ->add(PublicCatalogFixtures::BUSINESS_ID, AppointmentFixtures::customerSnapshot());
    $staff = (new FakeStaffDirectory($journal))
        ->add(PublicCatalogFixtures::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->slots = Mockery::mock(BookableSlots::class);
    $policies = Mockery::mock(CancellationPolicy::class);
    $policies->shouldReceive('forBusiness')->andReturn(CancellationRule::ofMinutes(120));
    $referenceCodes = Mockery::mock(ReferenceCodeGenerator::class);
    $referenceCodes->shouldReceive('next')->andReturn(ReferenceCode::fromString(PublicCatalogFixtures::REFERENCE_CODE));
    $manageTokens = Mockery::mock(ManageTokenFactory::class);
    $manageTokens->shouldReceive('issue')->andReturn(ManageToken::fromString(PublicCatalogFixtures::MANAGE_TOKEN));
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturn([]);

    $clock = new FakeClock(AppointmentFixtures::now());
    $presenter = new GuestBookingPresenter($services, $this->customers, $staff);
    $finder = new GuestBookingFinder($this->appointments);

    $this->desk = new AppointmentsGuestBookingDesk(
        new BookAppointmentAsGuest(
            $this->appointments,
            $services,
            $this->customers,
            $this->slots,
            $policies,
            $presenter,
            $referenceCodes,
            $manageTokens,
            new FixedIdGenerator(AppointmentFixtures::GENERATED_APPOINTMENT_ID),
            $clock,
            new FakeTransactionManager,
            $events,
        ),
        new RescheduleGuestBooking($this->appointments, $finder, $services, $this->slots, $policies, new AppointmentChangeWindow, $presenter, $clock, $events),
        new CancelGuestBooking($this->appointments, $finder, $policies, new AppointmentChangeWindow, $presenter, $clock, $events),
        new GuestBookingMapper,
    );

    $this->book = fn (?PublicGuestAddress $address = null, string $country = 'MX'): PublicGuestBookingConfirmation => $this->desk->book(
        PublicCatalogFixtures::BUSINESS_ID,
        PublicCatalogFixtures::bookingRequest(
            guest: PublicCatalogFixtures::guestDetails(address: $address),
            notes: 'Prefiero por la mañana.',
        ),
        $country,
    );

    $this->registeredGuest = fn (): GuestContact => $this->customers->guestRegistrations[0]['guest'];
});

describe('booking through the appointments domain', function () {
    beforeEach(function () {
        $this->slots->shouldReceive('isBookable')->andReturn(true);
    });

    it('hands back the confirmation the visitor is shown', function () {
        $confirmation = ($this->book)();

        expect($confirmation->booking->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($confirmation->manageToken)->toBe(PublicCatalogFixtures::MANAGE_TOKEN)
            ->and($confirmation->booking->startsAt)->toEqual(new DateTimeImmutable(PublicCatalogFixtures::STARTS_AT));
    });

    it('registers the guest under the business uuid it was given', function () {
        ($this->book)();

        expect($this->customers->guestRegistrations)->toHaveCount(1)
            ->and($this->customers->guestRegistrations[0]['businessId'])->toBe(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('passes the name, the email and the phone the visitor gave', function () {
        ($this->book)();

        $guest = ($this->registeredGuest)();

        expect($guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($guest->phone?->countryCode)->toBe(PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE)
            ->and($guest->phone?->nationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER);
    });

    it('files the address part by part under the country it was told', function () {
        ($this->book)(PublicCatalogFixtures::guestAddress(), 'ES');

        expect(($this->registeredGuest)()->address)->toEqual(new GuestAddress(
            street: PublicCatalogFixtures::GUEST_STREET,
            city: PublicCatalogFixtures::GUEST_CITY,
            stateName: PublicCatalogFixtures::GUEST_STATE,
            postalCode: PublicCatalogFixtures::GUEST_POSTAL_CODE,
            countryCode: 'ES',
        ));
    });

    it('files no address for a visitor who was asked for none', function () {
        ($this->book)(null);

        expect(($this->registeredGuest)()->address)->toBeNull();
    });
});

describe('a booking the appointments domain refuses', function () {
    it('throws the refusal, because a port answers with a verdict by throwing it', function () {
        $this->slots->shouldReceive('isBookable')->andReturn(false);

        expect(fn () => ($this->book)())->toThrow(AppointmentSlotNotBookable::class);
    });
});
