<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\AssignStaffToServiceInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\AssignStaffToService;
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

    $this->useCase = new AssignStaffToService(
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
        $this->services->store(ServiceFixtures::service(...$overrides));
    };

    $this->assign = fn (
        string $staffMemberId = ServiceFixtures::SECOND_STAFF_ID,
        string $serviceId = ServiceFixtures::SERVICE_ID,
    ) => $this->useCase->handle(new AssignStaffToServiceInput($serviceId, $staffMemberId));

    $this->storedStaff = fn (string $businessId = FakeBusinessContext::BUSINESS_ID) => $this->services
        ->findForBusiness($businessId, ServiceFixtures::SERVICE_ID)
        ->staffIds();
});

describe('assigning a staff member', function () {
    it('answers with the service as it now stands, offered by both', function () {
        ($this->onRecord)();

        $data = ($this->assign)()->value();

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
            ->and(array_map(static fn ($member) => $member->id, $data->staff))
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID])
            ->and(array_map(static fn ($member) => $member->name, $data->staff))
            ->toBe(['Ada Lovelace', 'Grace Hopper']);
    });

    it('saves the service once with the staff member added', function () {
        ($this->onRecord)();

        ($this->assign)();

        expect($this->services->saved)->toHaveCount(1)
            ->and($this->services->saved[0]->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($this->services->saved[0]->staffIds())
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('succeeds without doubling a staff member who already offers the service', function () {
        ($this->onRecord)(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);

        $response = ($this->assign)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->staff)->toHaveCount(2)
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('answers the same service when the same assignment is repeated', function () {
        ($this->onRecord)();

        $first = ($this->assign)()->value();
        $second = ($this->assign)()->value();

        expect($second)->toEqual($first)
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });
});

describe('the business it belongs to', function () {
    it('looks the service up under the business in context', function () {
        ($this->onRecord)();

        ($this->assign)();

        expect(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('checks that the staff member works at the business in context', function () {
        ($this->onRecord)();

        ($this->assign)();

        expect($this->staff->calls[0])->toBe([
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffIds' => [ServiceFixtures::SECOND_STAFF_ID],
        ]);
    });

    it('does not find a service that belongs to another business, and touches no service', function () {
        ($this->onRecord)(businessId: ServiceFixtures::OTHER_BUSINESS_ID);

        $response = ($this->assign)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)(ServiceFixtures::OTHER_BUSINESS_ID))->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('answers not found for a service nobody has', function () {
        $response = ($this->assign)();

        expect($response->error()->code)->toBe('service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->services->saved)->toBe([]);
    });

    it('refuses a staff member of another business and keeps the staff the service had', function () {
        ($this->onRecord)();

        $response = ($this->assign)(ServiceFixtures::FOREIGN_STAFF_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())->not->toContain(ServiceFixtures::FOREIGN_STAFF_ID)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)())->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('refuses a staff member nobody has', function () {
        ($this->onRecord)();

        $response = ($this->assign)('01930000-0000-7000-8000-0000000000d7');

        expect($response->error()->code)->toBe('unknown_staff_member')
            ->and($this->services->saved)->toBe([]);
    });
});

describe('refusing to assign', function () {
    it('refuses a staff member past the bound a service holds and saves nothing', function () {
        $full = array_map(static fn (int $n): string => sprintf('01930000-0000-7000-8000-%012d', $n), range(1, 50));
        ($this->onRecord)(staffIds: $full);

        $response = ($this->assign)();

        expect($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->services->saved)->toBe([])
            ->and(($this->storedStaff)())->toBe($full);
    });

    it('refuses identifiers the input refuses, without asking anyone', function (string $serviceId, string $staffMemberId, string $code) {
        ($this->onRecord)();

        $response = ($this->assign)($staffMemberId, $serviceId);

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
