<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBusinessPageInput;
use App\Domains\PublicCatalog\Application\Presenters\PublicBusinessPagePresenter;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicBusinessPage;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

/**
 * @return list<string>
 */
function publicPageCollaboratorTypes(string $class): array
{
    $constructor = (new ReflectionClass($class))->getConstructor();

    if ($constructor === null) {
        return [];
    }

    $types = [];

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            continue;
        }

        $types[] = $type->getName();
        $types = [...$types, ...publicPageCollaboratorTypes($type->getName())];
    }

    return $types;
}

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->brand = Mockery::mock(PublishedBrand::class);
    $this->schedule = Mockery::mock(PublishedSchedule::class);
    $this->services = Mockery::mock(PublishedServices::class);
    $this->team = Mockery::mock(PublishedTeam::class);
    $this->location = Mockery::mock(PublishedLocation::class);
    $this->contact = Mockery::mock(PublishedContact::class);

    $this->useCase = new ShowPublicBusinessPage(new PublicBusinessPagePresenter(
        $this->businesses,
        $this->brand,
        $this->schedule,
        $this->services,
        $this->team,
        $this->location,
        $this->contact,
    ));

    $this->sectionPorts = fn (): array => [
        $this->brand,
        $this->schedule,
        $this->services,
        $this->team,
        $this->location,
        $this->contact,
    ];

    $this->publish = function (): void {
        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->brand->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::brand());
        $this->schedule->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::scheduleEntry()]);
        $this->services->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::service()]);
        $this->team->shouldReceive('forBusiness')->once()->andReturn([PublicCatalogFixtures::teamMember()]);
        $this->location->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::location());
        $this->contact->shouldReceive('forBusiness')->once()->andReturn(PublicCatalogFixtures::contact());
    };

    $this->show = fn (string $slug = PublicCatalogFixtures::SLUG): UseCaseResponse => $this->useCase
        ->handle(new ShowPublicBusinessPageInput($slug));
});

describe('showing the page a visitor opened', function () {
    it('answers with every section of the page, field by field', function () {
        ($this->publish)();

        $response = ($this->show)();
        $page = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($page)->toBeInstanceOf(PublicBusinessPageData::class)
            ->and($page->profile->id)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($page->profile->name)->toBe(PublicCatalogFixtures::NAME)
            ->and($page->profile->slug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($page->profile->timezone)->toBe(PublicCatalogFixtures::TIMEZONE)
            ->and($page->profile->currencyCode)->toBe(PublicCatalogFixtures::CURRENCY_CODE)
            ->and($page->profile->logoUrl)->toBe(PublicCatalogFixtures::LOGO_URL)
            ->and($page->brand->bannerUrl)->toBe(PublicCatalogFixtures::BANNER_URL)
            ->and($page->schedule[0]->startsAt)->toBe('09:00')
            ->and($page->services[0]->slug)->toBe('corte-de-pelo')
            ->and($page->team[0]->name)->toBe('Ada Lovelace')
            ->and($page->location->countryCode)->toBe('MX')
            ->and($page->contact->phone)->toBe('+525512345678');
    });

    it('sends every id on the page as a uuid, never an internal key', function () {
        ($this->publish)();

        $page = ($this->show)()->value();

        $ids = [
            $page->profile->id,
            $page->services[0]->id,
            $page->team[0]->id,
            $page->brand->gallery[0]->id,
        ];

        expect($ids)->toBe([
            PublicCatalogFixtures::BUSINESS_ID,
            PublicCatalogFixtures::SERVICE_ID,
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            PublicCatalogFixtures::IMAGE_ID,
        ])->and(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('carries no warning on a page that is simply published', function () {
        ($this->publish)();

        expect(($this->show)()->warnings())->toBe([]);
    });
});

describe('a slug that answers to nothing', function () {
    it('answers with a refusal instead of throwing it', function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('resolves the business before anything else, so no other port is ever touched', function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        foreach (($this->sectionPorts)() as $port) {
            $port->shouldNotReceive('forBusiness');
        }

        expect(($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('keeps the refusal itself as the cause the responder renders', function () {
        $absent = BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG);

        $this->businesses->shouldReceive('findBySlug')->once()->andThrow($absent);

        expect(($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->error()->cause())->toBe($absent);
    });

    it('never hands an empty page back as a success', function () {
        $this->businesses->shouldReceive('findBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        expect(fn () => ($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->value())
            ->toThrow(BusinessPageNotFound::class);
    });
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a verdict', function () {
        $bug = new RuntimeException('the businesses table is gone');

        $this->businesses->shouldReceive('findBySlug')->once()->andThrow($bug);

        expect(fn () => ($this->show)())->toThrow($bug);
    });

    it('lets an error from a section out too, rather than serving half a page', function () {
        $bug = new RuntimeException('the media disk went away');

        $this->businesses->shouldReceive('findBySlug')->once()->andReturn(PublicCatalogFixtures::profile());
        $this->brand->shouldReceive('forBusiness')->once()->andThrow($bug);

        expect(fn () => ($this->show)())->toThrow($bug);
    });
});

describe('the tenant a public page runs under', function () {
    it('binds no business context anywhere in the graph it is built from', function () {
        $collaborators = publicPageCollaboratorTypes(ShowPublicBusinessPage::class);

        expect($collaborators)->toContain(PublicBusinessPagePresenter::class)
            ->and($collaborators)->toContain(PublishedBusinesses::class)
            ->and($collaborators)->not->toContain(BusinessContext::class)
            ->and($collaborators)->not->toContain('App\Models\User');
    });

    it('takes the slug as the only thing it is told, because a url segment is a public name', function () {
        $parameters = (new ReflectionMethod(ShowPublicBusinessPage::class, 'handle'))->getParameters();

        expect($parameters)->toHaveCount(1)
            ->and((string) $parameters[0]->getType())->toBe(ShowPublicBusinessPageInput::class)
            ->and(array_map(
                static fn (ReflectionProperty $property): string => $property->getName(),
                (new ReflectionClass(ShowPublicBusinessPageInput::class))->getProperties(),
            ))->toBe(['slug']);
    });
});
