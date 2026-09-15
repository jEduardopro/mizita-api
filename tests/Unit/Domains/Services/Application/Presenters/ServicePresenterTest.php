<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
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

    $this->images = new FakeServiceImages([
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
        $data = $this->presenter->describe(
            FakeBusinessContext::BUSINESS_ID,
            ServiceFixtures::service(color: ServiceColor::Amber),
        );

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
            ->and($data->bookingUrl)->toBe('https://mizita.test/b/ada-salon/corte-de-pelo')
            ->and($data->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('carries the staff of that service by name', function () {
        $data = $this->presenter->describe(
            FakeBusinessContext::BUSINESS_ID,
            ServiceFixtures::service(staffIds: [ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID]),
        );

        expect(array_map(static fn (object $member): string => $member->name, $data->staff))
            ->toBe(['Grace Hopper', 'Ada Lovelace']);
    });

    it('carries no image url when the service has no image', function () {
        $data = $this->presenter->describe(
            FakeBusinessContext::BUSINESS_ID,
            ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID),
        );

        expect($data->imageUrl)->toBeNull();
    });

    it('asks every collaborator about the business it was given', function () {
        $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::service());

        expect($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('does not carry the business it belongs to into the data', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::service());

        expect(get_object_vars($data))->not->toHaveKey('businessId');
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

        expect($this->images->urlsForCalls)->toHaveCount(1)
            ->and($this->images->urlForCalls)->toBe([])
            ->and($this->staff->callCount())->toBe(1)
            ->and($this->businesses->callCount())->toBe(1);
    });

    it('asks for the images of every service on the page in one call', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->images->urlsForCalls[0])->toBe([
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
            'https://mizita.test/b/ada-salon/corte-de-pelo',
            'https://mizita.test/b/ada-salon/barba',
            'https://mizita.test/b/ada-salon/tinte',
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

    it('asks for no staff at all when no service on the page has any', function () {
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
            ->and($this->images->urlsForCalls[0])->toBe([])
            ->and($this->staff->lastCall()['staffIds'])->toBe([]);
    });

    it('asks every collaborator about the business it was given', function () {
        $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, ($this->page)($this->services));

        expect($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});
