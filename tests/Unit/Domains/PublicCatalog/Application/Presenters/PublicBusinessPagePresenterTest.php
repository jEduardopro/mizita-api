<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Application\Presenters\PublicBusinessPagePresenter;
use App\Domains\PublicCatalog\Contracts\GuestContactFields;
use App\Domains\PublicCatalog\Contracts\PublishedBookingHorizon;
use App\Domains\PublicCatalog\Contracts\PublishedBookingPolicy;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->brand = Mockery::mock(PublishedBrand::class);
    $this->schedule = Mockery::mock(PublishedSchedule::class);
    $this->openState = Mockery::mock(PublishedOpenState::class);
    $this->bookingHorizon = Mockery::mock(PublishedBookingHorizon::class);
    $this->services = Mockery::mock(PublishedServices::class);
    $this->team = Mockery::mock(PublishedTeam::class);
    $this->location = Mockery::mock(PublishedLocation::class);
    $this->contact = Mockery::mock(PublishedContact::class);
    $this->bookingPolicy = Mockery::mock(PublishedBookingPolicy::class);
    $this->contactFields = Mockery::mock(GuestContactFields::class);

    $this->presenter = new PublicBusinessPagePresenter(
        $this->businesses,
        $this->brand,
        $this->schedule,
        $this->openState,
        $this->bookingHorizon,
        $this->services,
        $this->team,
        $this->location,
        $this->contact,
        $this->bookingPolicy,
        $this->contactFields,
    );

    $this->publish = function (?PublicBusinessProfile $profile = null): void {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->andReturn($profile ?? PublicCatalogFixtures::profile());

        $this->brand->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::brand());
        $this->schedule->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::scheduleEntry()]);
        $this->openState->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::openState());
        $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
            ->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
        $this->services->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::service()]);
        $this->team->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::teamMember()]);
        $this->location->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::location());
        $this->contact->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::contact());
        $this->bookingPolicy->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::bookingPolicy());
        $this->contactFields->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::contactFields());
    };

    $this->describe = fn (string $slug = PublicCatalogFixtures::SLUG): PublicBusinessPageData => $this->presenter
        ->describe($slug);
});

describe('assembling the page a visitor reads', function () {
    it('gathers every section behind the business the slug answers to', function () {
        ($this->publish)();

        $page = ($this->describe)();

        expect($page)->toBeInstanceOf(PublicBusinessPageData::class)
            ->and($page->profile->id)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($page->profile->name)->toBe(PublicCatalogFixtures::NAME)
            ->and($page->profile->slug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($page->brand->accentColor)->toBe('teal')
            ->and($page->schedule)->toHaveCount(1)
            ->and($page->schedule[0]->weekday)->toBe(1)
            ->and($page->services)->toHaveCount(1)
            ->and($page->services[0]->id)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($page->team)->toHaveCount(1)
            ->and($page->team[0]->id)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($page->location->city)->toBe('Ciudad de México')
            ->and($page->contact->links[0]->platform)->toBe('instagram');
    });

    it('looks the business up by the slug the url carried', function () {
        $asked = null;

        $this->businesses->shouldReceive('findBySlug')->once()
            ->with(Mockery::capture($asked))
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::SLUG));

        expect(fn () => ($this->describe)(PublicCatalogFixtures::SLUG))->toThrow(BusinessPageNotFound::class)
            ->and($asked)->toBe(PublicCatalogFixtures::SLUG);
    });

    it('asks every section about the business uuid, never about the slug', function () {
        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());

        $asked = [];
        $record = function (string $businessId) use (&$asked): bool {
            $asked[] = $businessId;

            return true;
        };

        $this->brand->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(PublicCatalogFixtures::brand());
        $this->schedule->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn([]);
        $this->openState->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(PublicCatalogFixtures::openState());
        $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
            ->with(Mockery::on($record))->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
        $this->services->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn([]);
        $this->team->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn([]);
        $this->location->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturnNull();
        $this->contact->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(PublicCatalogFixtures::contact());
        $this->bookingPolicy->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturnNull();
        $this->contactFields->shouldReceive('forBusiness')->once()
            ->with(Mockery::on($record))->andReturn(PublicCatalogFixtures::contactFields());

        ($this->describe)();

        expect($asked)->toBe(array_fill(0, 10, PublicCatalogFixtures::BUSINESS_ID));
    });

    it('carries the section each port answered with, without rewriting it', function () {
        $brand = PublicCatalogFixtures::brand(accentColor: 'amber', theme: 'light');
        $contact = PublicCatalogFixtures::contact(phone: '+34600123456');
        $contactFields = PublicCatalogFixtures::contactFields(
            phone: GuestFieldRequirement::Hidden,
            email: GuestFieldRequirement::Required,
            address: GuestFieldRequirement::Optional,
        );

        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->brand->shouldReceive('forBusiness')->once()->andReturn($brand);
        $this->schedule->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->openState->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::openState());
        $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
            ->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
        $this->services->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->team->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->location->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contact->shouldReceive('forBusiness')->once()->andReturn($contact);
        $this->bookingPolicy->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contactFields->shouldReceive('forBusiness')->once()->andReturn($contactFields);

        $page = ($this->describe)();

        expect($page->brand)->toBe($brand)
            ->and($page->contact)->toBe($contact)
            ->and($page->contactFields)->toBe($contactFields);
    });

    it('answers with a page whose sections are empty when the business filled none', function () {
        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->brand->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::brand(bannerUrl: null, gallery: []));
        $this->schedule->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->openState->shouldReceive('forBusiness')->once()
            ->andReturn(PublicOpenState::closedIndefinitely());
        $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
            ->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
        $this->services->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->team->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->location->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contact->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::contact(phone: null, links: []));
        $this->bookingPolicy->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contactFields->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::contactFields());

        $page = ($this->describe)();

        expect($page->bookingPolicy)->toBeNull()
            ->and($page->schedule)->toBe([])
            ->and($page->services)->toBe([])
            ->and($page->team)->toBe([])
            ->and($page->location)->toBeNull()
            ->and($page->contact->phone)->toBeNull()
            ->and($page->brand->gallery)->toBe([]);
    });

    it('describes whichever business the slug resolved to', function () {
        ($this->publish)(PublicCatalogFixtures::profile(
            id: PublicCatalogFixtures::OTHER_BUSINESS_ID,
            name: 'Peluquería Ámbar',
            slug: 'peluqueria-ambar',
        ));

        $page = ($this->describe)('peluqueria-ambar');

        expect($page->profile->id)->toBe(PublicCatalogFixtures::OTHER_BUSINESS_ID)
            ->and($page->profile->slug)->toBe('peluqueria-ambar');
    });
});

describe('the staff a service may be booked with', function () {
    beforeEach(function () {
        $this->publishWith = function (array $serviceStaffIds, array $team): void {
            $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
            $this->brand->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::brand());
            $this->schedule->shouldReceive('forBusiness')->once()->andReturn([]);
            $this->openState->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::openState());
            $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
                ->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
            $this->services->shouldReceive('forBusiness')->once()
                ->andReturn([PublicCatalogFixtures::service(staffIds: $serviceStaffIds)]);
            $this->team->shouldReceive('forBusiness')->once()->andReturn($team);
            $this->location->shouldReceive('forBusiness')->once()->andReturnNull();
            $this->contact->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::contact());
            $this->bookingPolicy->shouldReceive('forBusiness')->once()->andReturnNull();
            $this->contactFields->shouldReceive('forBusiness')->once()
                ->andReturn(PublicCatalogFixtures::contactFields());
        };
    });

    it('offers only the staff who are both on the service and on the published team', function () {
        ($this->publishWith)(
            [PublicCatalogFixtures::TEAM_MEMBER_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID],
            [PublicCatalogFixtures::teamMember()],
        );

        expect(($this->describe)()->services[0]->staffIds)->toBe([PublicCatalogFixtures::TEAM_MEMBER_ID]);
    });

    it('never offers a staff member the business removed from the team', function () {
        ($this->publishWith)(
            [PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID],
            [PublicCatalogFixtures::teamMember()],
        );

        $staffIds = ($this->describe)()->services[0]->staffIds;

        expect($staffIds)->toBe([])
            ->and($staffIds)->not->toContain(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID);
    });

    it('offers nothing for a service when the business publishes no team at all', function () {
        ($this->publishWith)([PublicCatalogFixtures::TEAM_MEMBER_ID], []);

        expect(($this->describe)()->services[0]->staffIds)->toBe([]);
    });

    it('offers every staff member the team still carries', function () {
        ($this->publishWith)(
            [PublicCatalogFixtures::TEAM_MEMBER_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID],
            [
                PublicCatalogFixtures::teamMember(),
                PublicCatalogFixtures::teamMember(
                    id: PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
                    name: 'Grace Hopper',
                ),
            ],
        );

        expect(($this->describe)()->services[0]->staffIds)->toBe([
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
        ]);
    });

    it('hands back a list with no gaps, so it serializes as a json array', function () {
        ($this->publishWith)(
            [PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID, PublicCatalogFixtures::TEAM_MEMBER_ID],
            [PublicCatalogFixtures::teamMember()],
        );

        $staffIds = ($this->describe)()->services[0]->staffIds;

        expect(array_keys($staffIds))->toBe([0])
            ->and(json_encode($staffIds, JSON_THROW_ON_ERROR))
            ->toBe('["'.PublicCatalogFixtures::TEAM_MEMBER_ID.'"]');
    });

    it('carries the staff uuid, never an internal key', function () {
        ($this->publishWith)([PublicCatalogFixtures::TEAM_MEMBER_ID], [PublicCatalogFixtures::teamMember()]);

        $staffIds = ($this->describe)()->services[0]->staffIds;

        expect(array_filter($staffIds, is_numeric(...)))->toBe([]);
    });
});

describe('whether the doors are open right now', function () {
    it('carries the open state the availability port worked out', function () {
        ($this->publish)();

        $page = ($this->describe)();

        expect($page->openState->isOpen())->toBeTrue()
            ->and($page->openState->closesAt)->toBe(PublicCatalogFixtures::CLOSES_AT)
            ->and($page->openState->opensOnWeekday)->toBeNull()
            ->and($page->openState->opensAt)->toBeNull();
    });

    it('carries when a closed business opens next', function () {
        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->brand->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::brand());
        $this->schedule->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->openState->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::closedState());
        $this->bookingHorizon->shouldReceive('lastBookableDateFor')->once()
            ->andReturn(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
        $this->services->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->team->shouldReceive('forBusiness')->once()->andReturn([]);
        $this->location->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contact->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::contact());
        $this->bookingPolicy->shouldReceive('forBusiness')->once()->andReturnNull();
        $this->contactFields->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::contactFields());

        $page = ($this->describe)();

        expect($page->openState->isOpen())->toBeFalse()
            ->and($page->openState->closesAt)->toBeNull()
            ->and($page->openState->opensOnWeekday)->toBe(PublicCatalogFixtures::OPENS_ON_WEEKDAY)
            ->and($page->openState->opensAt)->toBe(PublicCatalogFixtures::OPENS_AT);
    });

    it('carries the last date the visitor may still book', function () {
        ($this->publish)();

        expect(($this->describe)()->lastBookableDate)->toBe(PublicCatalogFixtures::LAST_BOOKABLE_DATE);
    });
});

describe('the contact fields a visitor is asked for', function () {
    it('carries the form settings of the business the slug answers to', function () {
        ($this->publish)();

        $fields = ($this->describe)()->contactFields;

        expect($fields)->toBeInstanceOf(GuestFormFields::class)
            ->and($fields->phone)->toBe(GuestFieldRequirement::Required)
            ->and($fields->email)->toBe(GuestFieldRequirement::Optional)
            ->and($fields->address)->toBe(GuestFieldRequirement::Hidden);
    });

    it('asks nothing about the form of a business the slug does not answer to', function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->contactFields->shouldNotReceive('forBusiness');

        expect(fn () => ($this->describe)(PublicCatalogFixtures::UNKNOWN_SLUG))->toThrow(BusinessPageNotFound::class);
    });
});

describe('a slug that answers to nothing', function () {
    it('lets the refusal out by throwing, because a presenter classifies nothing', function () {
        $absent = BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG);

        $this->businesses->shouldReceive('findBySlug')->once()->andThrow($absent);

        try {
            ($this->describe)(PublicCatalogFixtures::UNKNOWN_SLUG);
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($absent)
            ->and($thrown->errorCode())->toBe('business_not_found')
            ->and($thrown->kind())->toBe(DomainFailureKind::NotFound);
    });
});

describe('the ports it is built from', function () {
    it('takes every collaborator as a port of its own domain, so it is constructible with mocks alone', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(PublicBusinessPagePresenter::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([
            PublishedBusinesses::class,
            PublishedBrand::class,
            PublishedSchedule::class,
            PublishedOpenState::class,
            PublishedBookingHorizon::class,
            PublishedServices::class,
            PublishedTeam::class,
            PublishedLocation::class,
            PublishedContact::class,
            PublishedBookingPolicy::class,
            GuestContactFields::class,
        ])->and(array_filter($types, static fn (string $type): bool => ! interface_exists($type)))->toBe([]);
    });

    it('hands back the domain value objects, never a neighbour entity', function () {
        ($this->publish)();

        $page = ($this->describe)();

        expect($page->profile)->toBeInstanceOf(PublicBusinessProfile::class)
            ->and($page->brand)->toBeInstanceOf(PublicBrand::class)
            ->and($page->contact)->toBeInstanceOf(PublicContact::class);
    });
});
