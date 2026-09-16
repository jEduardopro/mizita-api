<?php

declare(strict_types=1);

use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\PublicCatalog\Infrastructure\Gateways\PhonesLinksPublishedContact;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicLink;
use Tests\Support\Links\LinkFixtures;
use Tests\Support\PhoneNumbers;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->phones = Mockery::mock(PhoneRepository::class);
    $this->links = Mockery::mock(LinkRepository::class);

    $this->gateway = new PhonesLinksPublishedContact($this->phones, $this->links);

    $this->businessPhone = fn (): Phone => Phone::restore(
        id: '01930000-0000-7000-8000-0000000000d1',
        ownerType: PhoneOwnerType::Business,
        ownerId: PublicCatalogFixtures::BUSINESS_ID,
        number: PhoneNumbers::mexican(),
        createdAt: LinkFixtures::now(),
    );

    $this->read = fn (): PublicContact => $this->gateway->forBusiness(PublicCatalogFixtures::BUSINESS_ID);
});

describe('how a visitor reaches the business', function () {
    it('publishes the number in the one format a link can dial', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn(($this->businessPhone)());
        $this->links->shouldReceive('allForOwner')->once()->andReturn([]);

        $contact = ($this->read)();

        expect($contact)->toBeInstanceOf(PublicContact::class)
            ->and($contact->phone)->toBe(PhoneNumbers::MX_E164)
            ->and($contact->links)->toBe([]);
    });

    it('sends no number for a business that filed none', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->links->shouldReceive('allForOwner')->once()->andReturn([]);

        expect(($this->read)()->phone)->toBeNull();
    });

    it('publishes each link as the platform and the url, and nothing else', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->links->shouldReceive('allForOwner')->once()->andReturn([
            LinkFixtures::link(
                ownerId: PublicCatalogFixtures::BUSINESS_ID,
                platform: LinkPlatform::Instagram,
                url: PublicCatalogFixtures::INSTAGRAM_URL,
                position: 0,
            ),
            LinkFixtures::link(
                id: LinkFixtures::SECOND_LINK_ID,
                ownerId: PublicCatalogFixtures::BUSINESS_ID,
                platform: LinkPlatform::Website,
                url: LinkFixtures::WEBSITE_URL,
                position: 10,
            ),
        ]);

        $links = ($this->read)()->links;

        expect($links)->toHaveCount(2)
            ->and($links[0])->toBeInstanceOf(PublicLink::class)
            ->and($links[0]->platform)->toBe('instagram')
            ->and($links[0]->url)->toBe(PublicCatalogFixtures::INSTAGRAM_URL)
            ->and($links[1]->platform)->toBe('website')
            ->and($links[1]->url)->toBe(LinkFixtures::WEBSITE_URL)
            ->and(array_map(
                static fn (ReflectionProperty $property): string => $property->getName(),
                (new ReflectionClass(PublicLink::class))->getProperties(),
            ))->toBe(['platform', 'url']);
    });

    it('keeps the links in the order the neighbour ordered them', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->links->shouldReceive('allForOwner')->once()->andReturn([
            LinkFixtures::link(platform: LinkPlatform::Website, url: LinkFixtures::WEBSITE_URL, position: 0),
            LinkFixtures::link(
                id: LinkFixtures::SECOND_LINK_ID,
                platform: LinkPlatform::Instagram,
                url: PublicCatalogFixtures::INSTAGRAM_URL,
                position: 10,
            ),
        ]);

        expect(array_column(($this->read)()->links, 'platform'))->toBe(['website', 'instagram']);
    });

    it('asks for both under the business uuid, as business contact details', function () {
        $phoneOwnerId = null;
        $linkOwnerId = null;

        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, Mockery::capture($phoneOwnerId))
            ->andReturnNull();
        $this->links->shouldReceive('allForOwner')->once()
            ->with(LinkOwnerType::Business, Mockery::capture($linkOwnerId))
            ->andReturn([]);

        ($this->read)();

        expect($phoneOwnerId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($linkOwnerId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and(is_numeric($phoneOwnerId))->toBeFalse();
    });

    it('publishes no row identity, since a visitor dials a number and does not edit one', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicContact::class))->getProperties(),
        );

        expect($fields)->toBe(['phone', 'links']);
    });

    it('answers with an empty contact section rather than nothing at all', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->links->shouldReceive('allForOwner')->once()->andReturn([]);

        $contact = ($this->read)();

        expect($contact)->toBeInstanceOf(PublicContact::class)
            ->and($contact->phone)->toBeNull()
            ->and($contact->links)->toBe([]);
    });
});
