<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ListServicesForStaffInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\ListServicesForStaff;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeOfferedServices;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->offered = new FakeOfferedServices;
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
        ServiceFixtures::SECOND_STAFF_ID => 'Grace Hopper',
    ])->add(ServiceFixtures::OTHER_BUSINESS_ID, [
        ServiceFixtures::FOREIGN_STAFF_ID => 'Katherine Johnson',
    ]);
    $this->images = FakeServiceImages::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
    ]);
    $this->businesses = new FakeBusinessProfile;

    $this->useCase = new ListServicesForStaff(
        $this->offered,
        new ServicePresenter(
            $this->staff,
            $this->images,
            $this->businesses,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->list = fn (string $staffMemberId = ServiceFixtures::STAFF_ID) => $this->useCase
        ->handle(new ListServicesForStaffInput($staffMemberId));
});

describe('listing what a staff member offers', function () {
    it('answers with every service the staff member offers, described in full', function () {
        $this->offered->store(
            ServiceFixtures::service(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]),
            ServiceFixtures::service(
                id: ServiceFixtures::SECOND_SERVICE_ID,
                name: 'Barba',
                slug: 'barba',
                description: null,
                durationMinutes: 20,
                bufferMinutes: 0,
                price: '120.00',
                color: ServiceColor::Sand,
                active: false,
            ),
        );

        $services = ($this->list)()->value();

        expect($services)->toHaveCount(2)
            ->and($services[0])->toBeInstanceOf(ServiceData::class)
            ->and($services[0]->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($services[0]->name)->toBe(ServiceFixtures::NAME)
            ->and($services[0]->slug)->toBe(ServiceFixtures::SLUG)
            ->and($services[0]->description)->toBe('Incluye lavado.')
            ->and($services[0]->durationMinutes)->toBe(45)
            ->and($services[0]->bufferMinutes)->toBe(10)
            ->and($services[0]->price)->toBe('250.00')
            ->and($services[0]->color)->toBe(ServiceColor::Teal)
            ->and($services[0]->active)->toBeTrue()
            ->and($services[0]->imageUrl)->toBe('https://cdn.mizita.test/corte.png')
            ->and($services[0]->bookingUrl)->toBe('https://mizita.test/ada-salon/corte-de-pelo')
            ->and($services[0]->createdAt)->toEqual(ServiceFixtures::now())
            ->and(array_map(static fn ($member) => $member->id, $services[0]->staff))
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID])
            ->and(array_map(static fn ($member) => $member->name, $services[0]->staff))
            ->toBe(['Ada Lovelace', 'Grace Hopper'])
            ->and($services[1]->id)->toBe(ServiceFixtures::SECOND_SERVICE_ID)
            ->and($services[1]->name)->toBe('Barba')
            ->and($services[1]->description)->toBeNull()
            ->and($services[1]->active)->toBeFalse()
            ->and($services[1]->imageUrl)->toBeNull()
            ->and($services[1]->bookingUrl)->toBe('https://mizita.test/ada-salon/barba');
    });

    it('keeps the order the services were found in', function () {
        $this->offered->store(
            ServiceFixtures::service(id: ServiceFixtures::THIRD_SERVICE_ID, name: 'Tinte', slug: 'tinte'),
            ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID, name: 'Barba', slug: 'barba'),
        );

        expect(array_map(static fn (ServiceData $service) => $service->id, ($this->list)()->value()))
            ->toBe([ServiceFixtures::THIRD_SERVICE_ID, ServiceFixtures::SECOND_SERVICE_ID]);
    });

    it('leaves out the services the staff member does not offer', function () {
        $this->offered->store(
            ServiceFixtures::service(),
            ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID, staffIds: [ServiceFixtures::SECOND_STAFF_ID]),
        );

        expect(array_map(static fn (ServiceData $service) => $service->id, ($this->list)()->value()))
            ->toBe([ServiceFixtures::SERVICE_ID]);
    });

    it('answers an empty list with a success, not a refusal', function () {
        $response = ($this->list)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });
});

describe('the business it belongs to', function () {
    it('asks only for the services of the business in context', function () {
        ($this->list)();

        expect($this->offered->calls)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffId' => ServiceFixtures::STAFF_ID,
        ]]);
    });

    it('leaves out the services another business has, even offered under the same staff identifier', function () {
        $this->offered->store(
            ServiceFixtures::service(),
            ServiceFixtures::service(
                id: ServiceFixtures::SECOND_SERVICE_ID,
                businessId: ServiceFixtures::OTHER_BUSINESS_ID,
                name: 'Servicio ajeno',
                slug: 'servicio-ajeno',
            ),
        );

        expect(array_map(static fn (ServiceData $service) => $service->id, ($this->list)()->value()))
            ->toBe([ServiceFixtures::SERVICE_ID]);
    });

    it('answers nothing for a staff member of another business rather than their services', function () {
        $this->offered->store(ServiceFixtures::service(
            businessId: ServiceFixtures::OTHER_BUSINESS_ID,
            staffIds: [ServiceFixtures::FOREIGN_STAFF_ID],
        ));

        $response = ($this->list)(ServiceFixtures::FOREIGN_STAFF_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });

    it('describes the services with the images, staff and address of the business in context', function () {
        $this->offered->store(ServiceFixtures::service());

        ($this->list)();

        expect(array_column($this->images->batchReads, 'businessId'))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_unique(array_column($this->staff->calls, 'businessId')))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('refusing to list', function () {
    it('refuses an identifier that cannot be a staff member, without looking anything up', function (string $staffMemberId) {
        $this->offered->store(ServiceFixtures::service());

        $response = ($this->list)($staffMemberId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->offered->calls)->toBe([])
            ->and($this->staff->calls)->toBe([]);
    })->with([
        'empty' => '',
        'an integer key' => '7',
        'not a uuid' => 'ada-lovelace',
    ]);
});
