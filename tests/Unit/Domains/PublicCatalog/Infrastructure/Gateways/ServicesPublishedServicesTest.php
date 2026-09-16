<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\ServicesPublishedServices;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->images = new FakeServiceImages;

    $this->gateway = new ServicesPublishedServices($this->services, $this->images);

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): array => $this->gateway
        ->forBusiness($businessId);
});

describe('the services a visitor may book', function () {
    it('translates each service into what the booking page shows', function () {
        $this->services->store(ServiceFixtures::service(
            id: PublicCatalogFixtures::SERVICE_ID,
            businessId: PublicCatalogFixtures::BUSINESS_ID,
        ));

        $published = ($this->read)();

        expect($published)->toHaveCount(1)
            ->and($published[0])->toBeInstanceOf(PublicService::class)
            ->and($published[0]->id)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($published[0]->name)->toBe(ServiceFixtures::NAME)
            ->and($published[0]->slug)->toBe(ServiceFixtures::SLUG)
            ->and($published[0]->description)->toBe('Incluye lavado.')
            ->and($published[0]->durationMinutes)->toBe(45)
            ->and($published[0]->price)->toBe('250.00');
    });

    it('publishes the duration a customer waits, never the buffer the business keeps', function () {
        $this->services->store(ServiceFixtures::service(
            businessId: PublicCatalogFixtures::BUSINESS_ID,
            durationMinutes: 45,
            bufferMinutes: 15,
        ));

        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicService::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'name', 'slug', 'description', 'durationMinutes', 'price', 'imageUrl'])
            ->and($fields)->not->toContain('bufferMinutes')
            ->and($fields)->not->toContain('color')
            ->and($fields)->not->toContain('staffIds')
            ->and(($this->read)()[0]->durationMinutes)->toBe(45);
    });

    it('carries the service uuid, never an internal key', function () {
        $this->services->store(ServiceFixtures::service(
            id: PublicCatalogFixtures::SERVICE_ID,
            businessId: PublicCatalogFixtures::BUSINESS_ID,
        ));

        $id = ($this->read)()[0]->id;

        expect($id)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->toBeString()
            ->and(is_numeric($id))->toBeFalse();
    });

    it('asks the catalogue for the active services of that business alone', function () {
        ($this->read)();

        expect($this->services->businessIdsSeen)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });

    it('leaves out a service the business took off the page', function () {
        $this->services->store(
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SECOND_SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                name: 'Barba',
                slug: 'barba',
                active: false,
            ),
        );

        expect(array_column(($this->read)(), 'id'))->toBe([PublicCatalogFixtures::SERVICE_ID]);
    });

    it('never publishes the services of another business', function () {
        $this->services->store(
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SECOND_SERVICE_ID,
                businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID,
                name: 'Barba',
                slug: 'barba',
            ),
        );

        expect(array_column(($this->read)(), 'id'))->toBe([PublicCatalogFixtures::SERVICE_ID])
            ->and(array_column(($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID), 'id'))
            ->toBe([PublicCatalogFixtures::SECOND_SERVICE_ID]);
    });

    it('answers with an empty list for a business with nothing bookable', function () {
        expect(($this->read)())->toBe([]);
    });
});

describe('the image each service shows', function () {
    beforeEach(function () {
        $this->services->store(
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SECOND_SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                name: 'Barba',
                slug: 'barba',
            ),
        );
    });

    it('asks for every url in one call, rather than one call per service', function () {
        ($this->read)();

        expect($this->images->urlsForCalls)->toHaveCount(1)
            ->and($this->images->urlsForCalls[0])->toBe([
                PublicCatalogFixtures::SECOND_SERVICE_ID,
                PublicCatalogFixtures::SERVICE_ID,
            ])
            ->and($this->images->urlForCalls)->toBe([]);
    });

    it('hands each service the url filed under its own id', function () {
        $images = new FakeServiceImages([
            PublicCatalogFixtures::SERVICE_ID => PublicCatalogFixtures::SERVICE_IMAGE_URL,
        ]);

        $published = (new ServicesPublishedServices($this->services, $images))
            ->forBusiness(PublicCatalogFixtures::BUSINESS_ID);

        $urlsById = array_combine(array_column($published, 'id'), array_column($published, 'imageUrl'));

        expect($urlsById[PublicCatalogFixtures::SERVICE_ID])->toBe(PublicCatalogFixtures::SERVICE_IMAGE_URL)
            ->and($urlsById[PublicCatalogFixtures::SECOND_SERVICE_ID])->toBeNull();
    });

    it('asks for no image at all when the business has no active service', function () {
        expect(($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID))->toBe([])
            ->and($this->images->urlsForCalls)->toBe([]);
    });
});
