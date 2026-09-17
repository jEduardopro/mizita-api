<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
        ServiceFixtures::SECOND_STAFF_ID => 'Grace Hopper',
    ]);

    $this->images = FakeServiceImages::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
    ]);

    $this->businesses = new FakeBusinessProfile;

    $this->presenter = new ServicePresenter(
        $this->staff,
        $this->images,
        $this->businesses,
        new BookingLinks(ServiceFixtures::BASE_URL),
    );

    $this->page = fn (array $services, int $total = 3, ?Pagination $pagination = null): Paginated => Paginated::of(
        $services,
        $total,
        $pagination ?? Pagination::of(1, 20),
    );
});

describe('describing one service', function () {
    it('fills every field of the data the client reads', function () {
        $data = $this->presenter->describe(ServiceFixtures::service(color: ServiceColor::Amber));

        expect($data)->toBeInstanceOf(ServiceData::class)
            ->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($data->name)->toBe(ServiceFixtures::NAME)
            ->and($data->slug)->toBe(ServiceFixtures::SLUG)
            ->and($data->description)->toBe('Incluye lavado.')
            ->and($data->durationMinutes)->toBe(45)
            ->and($data->bufferMinutes)->toBe(10)
            ->and($data->price)->toBe('250.00')
            ->and($data->color)->toBe(ServiceColor::Amber)
            ->and($data->active)->toBeTrue()
            ->and($data->imageUrl)->toBe('https://cdn.mizita.test/corte.png')
            ->and($data->bookingUrl)->toBe('https://mizita.test/ada-salon/corte-de-pelo')
            ->and($data->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('carries the staff of that service by name', function () {
        $data = $this->presenter->describe(ServiceFixtures::service(
            staffIds: [ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID],
        ));

        expect(array_map(static fn (object $member): string => $member->name, $data->staff))
            ->toBe(['Grace Hopper', 'Ada Lovelace']);
    });

    it('carries no image url when the service has no image', function () {
        $data = $this->presenter->describe(ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID));

        expect($data->imageUrl)->toBeNull();
    });

    it('does not carry the business it belongs to into the data', function () {
        $data = $this->presenter->describe(ServiceFixtures::service());

        expect(get_object_vars($data))->not->toHaveKey('businessId');
    });
});

describe('the business a described service is read under', function () {
    it('takes the business from the service alone, never from an argument of its own', function () {
        $parameters = (new ReflectionMethod(ServicePresenter::class, 'describe'))->getParameters();

        expect(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName().':'.$parameter->getType(),
            $parameters,
        ))->toBe(['service:'.Service::class]);
    });

    it('asks every collaborator about the business the service carries', function () {
        $this->presenter->describe(ServiceFixtures::service());

        expect($this->images->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'serviceId' => ServiceFixtures::SERVICE_ID,
        ]])->and($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('reads a neighbouring business service under that neighbour, not under the first business it saw', function () {
        $this->presenter->describe(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        expect($this->images->reads[0]['businessId'])->toBe(ServiceFixtures::OTHER_BUSINESS_ID)
            ->and($this->staff->lastCall()['businessId'])->toBe(ServiceFixtures::OTHER_BUSINESS_ID)
            ->and($this->businesses->calls)->toBe([ServiceFixtures::OTHER_BUSINESS_ID]);
    });

    it('hands a neighbouring business service none of the first business files', function () {
        $data = $this->presenter->describe(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        expect($data->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($data->imageUrl)->toBeNull();
    });

    it('hands it the file its own business filed under that same id', function () {
        $this->images->add(ServiceFixtures::OTHER_BUSINESS_ID, [
            ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/otro.png',
        ]);

        $data = $this->presenter->describe(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        expect($data->imageUrl)->toBe('https://cdn.mizita.test/otro.png');
    });
});

describe('describing a page of services', function () {
    beforeEach(function () {
        $this->services = [
            ServiceFixtures::service(
                id: ServiceFixtures::SERVICE_ID,
                name: 'Corte de pelo',
                slug: 'corte-de-pelo',
                staffIds: [ServiceFixtures::STAFF_ID],
            ),
            ServiceFixtures::service(
                id: ServiceFixtures::SECOND_SERVICE_ID,
                name: 'Barba',
                slug: 'barba',
                staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID],
            ),
            ServiceFixtures::service(
                id: ServiceFixtures::THIRD_SERVICE_ID,
                name: 'Tinte',
                slug: 'tinte',
                staffIds: [ServiceFixtures::SECOND_STAFF_ID],
            ),
        ];
    });

    it('asks each collaborator once for the whole page, never once per service', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->images->batchReads)->toHaveCount(1)
            ->and($this->images->reads)->toBe([])
            ->and($this->staff->callCount())->toBe(1)
            ->and($this->businesses->callCount())->toBe(1);
    });

    it('asks for the images of every service on the page in one call', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->images->batchReads[0]['serviceIds'])->toBe([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ]);
    });

    it('asks for each staff member once however many services share them', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->staff->lastCall())->toBe([
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffIds' => [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID],
        ]);
    });

    it('gives each service its own staff, in the order the service holds them', function () {
        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        $names = array_map(
            static fn (ServiceData $data): array => array_map(
                static fn (object $member): string => $member->name,
                $data->staff,
            ),
            $page->items,
        );

        expect($names)->toBe([
            ['Ada Lovelace'],
            ['Ada Lovelace', 'Grace Hopper'],
            ['Grace Hopper'],
        ]);
    });

    it('drops a staff member the directory does not hand back rather than emptying the row', function () {
        $services = [ServiceFixtures::service(staffIds: [
            ServiceFixtures::STAFF_ID,
            ServiceFixtures::FOREIGN_STAFF_ID,
        ])];

        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($services, 1));

        expect(array_map(static fn (object $member): string => $member->id, $page->items[0]->staff))
            ->toBe([ServiceFixtures::STAFF_ID]);
    });

    it('gives each service the image url that belongs to it, and none to the rest', function () {
        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect(array_map(static fn (ServiceData $data): ?string => $data->imageUrl, $page->items))
            ->toBe(['https://cdn.mizita.test/corte.png', null, null]);
    });

    it('builds every booking link from the one business slug it looked up', function () {
        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect(array_map(static fn (ServiceData $data): string => $data->bookingUrl, $page->items))->toBe([
            'https://mizita.test/ada-salon/corte-de-pelo',
            'https://mizita.test/ada-salon/barba',
            'https://mizita.test/ada-salon/tinte',
        ]);
    });

    it('keeps the total, the page and the page size it was handed', function () {
        $page = $this->presenter->describePage(
            FakeBusinessContext::BUSINESS_ID,
            ($this->page)($this->services, 42, Pagination::of(3, 25)),
        );

        expect($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(25)
            ->and($page->lastPage())->toBe(2)
            ->and($page->items)->toHaveCount(3);
    });

    it('asks for no staff at all when every service on the page was saved before a team was required', function () {
        $services = [ServiceFixtures::service(staffIds: []), ServiceFixtures::service(
            id: ServiceFixtures::SECOND_SERVICE_ID,
            staffIds: [],
        )];

        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($services, 2));

        expect($this->staff->lastCall()['staffIds'])->toBe([])
            ->and($page->items[0]->staff)->toBe([]);
    });

    it('describes an empty page without asking for anything it does not need', function () {
        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)([], 0));

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(0)
            ->and($this->images->batchReads[0]['serviceIds'])->toBe([])
            ->and($this->staff->lastCall()['staffIds'])->toBe([]);
    });

    it('asks every collaborator about the business it was given', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->images->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('hands out none of a neighbouring business files, whatever ids the page carries', function () {
        $this->images->add(ServiceFixtures::OTHER_BUSINESS_ID, [
            ServiceFixtures::SECOND_SERVICE_ID => 'https://cdn.mizita.test/otro.png',
        ]);

        $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect(array_map(static fn (ServiceData $data): ?string => $data->imageUrl, $page->items))
            ->toBe(['https://cdn.mizita.test/corte.png', null, null]);
    });

    it('still takes the business as an argument, because an empty page carries no service to read it from', function () {
        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod(ServicePresenter::class, 'describePage'))->getParameters(),
        );

        expect($parameters)->toBe(['businessId', 'page']);
    });
});
