<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicAvailabilityInput;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicAvailability;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\Contracts\PublishedSlots;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->slots = Mockery::mock(PublishedSlots::class);
    $this->openState = Mockery::mock(PublishedOpenState::class);
    $this->openState->shouldReceive('forBusiness')
        ->andReturn(PublicCatalogFixtures::openState())
        ->byDefault();

    $this->useCase = new ShowPublicAvailability($this->businesses, $this->slots, $this->openState);

    $this->show = fn (string $slug = PublicCatalogFixtures::SLUG, ...$overrides): UseCaseResponse => $this->useCase
        ->handle(new ShowPublicAvailabilityInput($slug, PublicCatalogFixtures::slotQuery(...$overrides)));
});

describe('a visitor reading the days they may book', function () {
    it('answers with the days the slot engine worked out', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([
            PublicCatalogFixtures::availableDay('2026-03-10'),
            PublicCatalogFixtures::availableDay('2026-03-11', []),
        ]);

        $days = ($this->show)()->value();

        expect($days)->toHaveCount(2)
            ->and($days[0])->toBeInstanceOf(PublicAvailableDay::class)
            ->and($days[0]->date)->toBe('2026-03-10')
            ->and($days[0]->starts[0])->toEqual(new DateTimeImmutable(PublicCatalogFixtures::STARTS_AT))
            ->and($days[1]->date)->toBe('2026-03-11')
            ->and($days[1]->starts)->toBe([]);
    });

    it('resolves the slug to a business uuid before it asks for a slot', function () {
        $askedFor = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedFor), Mockery::any())
            ->andReturn([]);

        ($this->show)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($askedFor)->not->toBe(PublicCatalogFixtures::SLUG);
    });

    it('hands the slot engine the query the visitor sent, untouched', function () {
        $asked = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()
            ->with(Mockery::any(), Mockery::capture($asked))
            ->andReturn([]);

        ($this->show)(PublicCatalogFixtures::SLUG, serviceId: PublicCatalogFixtures::SECOND_SERVICE_ID);

        expect($asked->serviceId)->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID)
            ->and($asked->staffId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID);
    });

    it('answers with an empty list for a business with nothing free', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([]);

        expect(($this->show)()->value())->toBe([]);
    });
});

describe('a business whose doors are shut right now', function () {
    beforeEach(function () {
        $this->closeUntilMonday = function (): void {
            $this->openState->shouldReceive('forBusiness')->andReturn(PublicCatalogFixtures::closedState());
        };
    });

    it('offers no start on any day, not even one weeks ahead', function () {
        ($this->closeUntilMonday)();
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([
            PublicCatalogFixtures::availableDay('2026-03-10'),
            PublicCatalogFixtures::availableDay('2026-03-11', [PublicCatalogFixtures::STARTS_AT]),
            PublicCatalogFixtures::availableDay('2026-06-30', [PublicCatalogFixtures::STARTS_AT]),
        ]);

        $days = ($this->show)()->value();

        expect(array_column($days, 'starts'))->toBe([[], [], []]);
    });

    it('keeps the date of every day, so the calendar still renders its month', function () {
        ($this->closeUntilMonday)();
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([
            PublicCatalogFixtures::availableDay('2026-03-10'),
            PublicCatalogFixtures::availableDay('2026-03-11'),
            PublicCatalogFixtures::availableDay('2026-03-12'),
        ]);

        $days = ($this->show)()->value();

        expect(array_column($days, 'date'))->toBe(['2026-03-10', '2026-03-11', '2026-03-12'])
            ->and($days)->toHaveCount(3)
            ->and($days[0])->toBeInstanceOf(PublicAvailableDay::class);
    });

    it('answers with a success, because an empty calendar is not a refusal', function () {
        ($this->closeUntilMonday)();
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::availableDay()]);

        expect(($this->show)()->succeeded())->toBeTrue();
    });

    it('offers nothing for a business that is closed with no next opening either', function () {
        $this->openState->shouldReceive('forBusiness')->andReturn(PublicOpenState::closedIndefinitely());
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::availableDay()]);

        expect(($this->show)()->value()[0]->starts)->toBe([]);
    });

    it('offers the starts the slot engine worked out while the doors are open', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::availableDay()]);

        expect(($this->show)()->value()[0]->starts)->toHaveCount(1);
    });

    it('asks about the business the slug resolved to, never about the slug', function () {
        $asked = null;

        $this->openState->shouldReceive('forBusiness')
            ->with(Mockery::capture($asked))
            ->andReturn(PublicCatalogFixtures::closedState());
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::availableDay()]);

        ($this->show)();

        expect($asked)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($asked)->not->toBe(PublicCatalogFixtures::SLUG);
    });
});

describe('a slug that answers to nothing', function () {
    it('answers with a not found refusal, never a forbidden one', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('asks the slot engine nothing at all when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->slots->shouldNotReceive('forBusiness');

        expect(($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->slots->shouldNotReceive('forBusiness');

        $response = ($this->show)($slug);

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
        'trailing dash' => 'ada-',
        'spaces' => 'ada salon',
        'too long' => 'a-very-long-slug-that-goes-on-and-on-and-on-and-on-past-the-limit',
    ]);

    it('never says a slug exists by answering differently for a malformed one', function () {
        $this->businesses->shouldReceive('identifyBySlug')
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $unknown = ($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->error();
        $malformed = ($this->show)('Ada-Salon')->error();

        expect($unknown->code)->toBe($malformed->code)
            ->and($unknown->kind)->toBe($malformed->kind);
    });
});

describe('the tenant a public read runs under', function () {
    it('binds no business context, because a public read is resolved from a url segment', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ShowPublicAvailability::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, PublishedSlots::class, PublishedOpenState::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });

    it('carries no tenant record on the day it hands back', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->slots->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::availableDay()]);

        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicAvailableDay::class))->getProperties(),
        );

        expect($fields)->toBe(['date', 'starts'])
            ->and($fields)->not->toContain('businessId')
            ->and($fields)->not->toContain('customerId')
            ->and($fields)->not->toContain('appointmentId')
            ->and(json_encode(($this->show)()->value(), JSON_THROW_ON_ERROR))
            ->not->toContain(PublicCatalogFixtures::BUSINESS_ID);
    });
});
