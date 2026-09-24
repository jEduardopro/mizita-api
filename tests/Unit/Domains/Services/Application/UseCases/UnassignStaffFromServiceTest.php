<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Dtos\UnassignStaffFromServiceInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\UnassignStaffFromService;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
        ServiceFixtures::SECOND_STAFF_ID => 'Grace Hopper',
    ])->add(ServiceFixtures::OTHER_BUSINESS_ID, [
        ServiceFixtures::FOREIGN_STAFF_ID => 'Katherine Johnson',
    ]);

    $this->useCase = new UnassignStaffFromService(
        $this->services,
        $this->staff,
        new ServicePresenter(
            $this->staff,
            FakeServiceImages::of(FakeBusinessContext::BUSINESS_ID, [
                ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
            ]),
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->onRecord = function (...$overrides) {
        $this->services->store(ServiceFixtures::service(...[
            'staffIds' => [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID],
            ...$overrides,
        ]));
    };

    $this->unassign = fn (
        string $staffMemberId = ServiceFixtures::SECOND_STAFF_ID,
        string $serviceId = ServiceFixtures::SERVICE_ID,
    ) => $this->useCase->handle(new UnassignStaffFromServiceInput($serviceId, $staffMemberId));

    $this->storedStaff = fn (string $businessId = FakeBusinessContext::BUSINESS_ID) => $this->services
        ->findForBusiness($businessId, ServiceFixtures::SERVICE_ID)
        ->staffIds();
});

describe('unassigning a staff member', function () {
    it('answers with the service as it now stands, offered only by the one left', function () {
        ($this->onRecord)();

        $data = ($this->unassign)()->value();

        expect($data)->toBeInstanceOf(ServiceData::class)
            ->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($data->name)->toBe(ServiceFixtures::NAME)
            ->and($data->slug)->toBe(ServiceFixtures::SLUG)
            ->and($data->description)->toBe('Incluye lavado.')
            ->and($data->durationMinutes)->toBe(45)
            ->and($data->bufferMinutes)->toBe(10)
            ->and($data->price)->toBe('250.00')
            ->and($data->color)->toBe(ServiceColor::Teal)
            ->and($data->active)->toBeTrue()
            ->and($data->imageUrl)->toBe('https://cdn.mizita.test/corte.png')
            ->and($data->bookingUrl)->toBe('https://mizita.test/ada-salon/corte-de-pelo')
            ->and($data->createdAt)->toEqual(ServiceFixtures::now())
            ->and($data->staff)->toHaveCount(1)
            ->and($data->staff[0]->id)->toBe(ServiceFixtures::STAFF_ID)
            ->and($data->staff[0]->name)->toBe('Ada Lovelace');
    });

    it('saves the service once without the staff member', function () {
        ($this->onRecord)();

        ($this->unassign)();

        expect($this->services->saved)->toHaveCount(1)
            ->and($this->services->saved[0]->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($this->services->saved[0]->staffIds())->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('succeeds without change for a staff member of the business who does not offer the service', function () {
        ($this->onRecord)(staffIds: [ServiceFixtures::STAFF_ID]);

        $response = ($this->unassign)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->staff)->toHaveCount(1)
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('answers the same service when the same unassignment is repeated', function () {
        ($this->onRecord)();

        $first = ($this->unassign)()->value();
        $second = ($this->unassign)()->value();

        expect($second)->toEqual($first)
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('refuses to take away the last staff member and saves nothing', function () {
        ($this->onRecord)(staffIds: [ServiceFixtures::SECOND_STAFF_ID]);

        $response = ($this->unassign)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_requires_staff')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::SECOND_STAFF_ID]);
    });
});

describe('the business it belongs to', function () {
    it('looks the service up under the business in context', function () {
        ($this->onRecord)();

        ($this->unassign)();

        expect(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('checks that the staff member works at the business in context', function () {
        ($this->onRecord)();

        ($this->unassign)();

        expect($this->staff->calls[0])->toBe([
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffIds' => [ServiceFixtures::SECOND_STAFF_ID],
        ]);
    });

    it('does not find a service that belongs to another business, and touches no service', function () {
        ($this->onRecord)(businessId: ServiceFixtures::OTHER_BUSINESS_ID);

        $response = ($this->unassign)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)(ServiceFixtures::OTHER_BUSINESS_ID))
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('answers not found for a service nobody has', function () {
        $response = ($this->unassign)();

        expect($response->error()->code)->toBe('service_not_found')
            ->and($this->services->saved)->toBe([]);
    });

    it('refuses a staff member of another business and keeps the staff the service had', function () {
        ($this->onRecord)(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::FOREIGN_STAFF_ID]);

        $response = ($this->unassign)(ServiceFixtures::FOREIGN_STAFF_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::FOREIGN_STAFF_ID]);
    });

    it('refuses a staff member nobody has', function () {
        ($this->onRecord)();

        $response = ($this->unassign)('01930000-0000-7000-8000-0000000000d7');

        expect($response->error()->code)->toBe('unknown_staff_member')
            ->and($this->services->saved)->toBe([]);
    });

    it('checks the staff member before deciding the service would be left with nobody', function () {
        ($this->onRecord)(staffIds: [ServiceFixtures::FOREIGN_STAFF_ID]);

        expect(($this->unassign)(ServiceFixtures::FOREIGN_STAFF_ID)->error()->code)->toBe('unknown_staff_member');
    });
});

describe('refusing to unassign', function () {
    it('refuses identifiers the input refuses, without asking anyone', function (string $serviceId, string $staffMemberId, string $code) {
        ($this->onRecord)();

        $response = ($this->unassign)($staffMemberId, $serviceId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->services->businessIdsSeen)->toBe([])
            ->and($this->staff->calls)->toBe([])
            ->and($this->services->saved)->toBe([]);
    })->with([
        'a service that cannot be one' => ['not-a-uuid', ServiceFixtures::SECOND_STAFF_ID, 'service_not_found'],
        'a staff member that cannot be one' => [ServiceFixtures::SERVICE_ID, '42', 'unknown_staff_member'],
        'neither' => ['', '', 'service_not_found'],
    ]);
});
