<?php

declare(strict_types=1);

use App\Domains\Links\Application\Dtos\LinkData;
use App\Domains\Links\Application\Dtos\ReplaceLinksInput;
use App\Domains\Links\Application\UseCases\ReplaceLinks;
use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkTarget;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\IdGenerator;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Links\LinkFixtures;

/**
 * @param  list<LinkTarget>  $targets
 */
function replaceLinksInput(
    array $targets = [],
    LinkOwnerType $ownerType = LinkOwnerType::Business,
    string $ownerId = FakeBusinessContext::BUSINESS_ID,
): ReplaceLinksInput {
    return new ReplaceLinksInput($ownerType, $ownerId, $targets);
}

beforeEach(function () {
    $this->links = Mockery::mock(LinkRepository::class);
    $this->useCase = new ReplaceLinks(
        $this->links,
        new FixedIdGenerator(LinkFixtures::LINK_ID, LinkFixtures::SECOND_LINK_ID, LinkFixtures::THIRD_LINK_ID),
        new FakeClock(LinkFixtures::now()),
    );
});

it('hands the repository the whole new list in one call', function () {
    $this->links->shouldReceive('replaceForOwner')->once()
        ->with(
            LinkOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
            Mockery::on(fn (array $links): bool => count($links) === 2),
        );

    $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
        LinkFixtures::target(),
    ]));
});

it('returns the saved links as data, field by field', function () {
    $this->links->shouldReceive('replaceForOwner')->once();

    $data = $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
    ]))->value();

    expect($data)->toHaveCount(1)
        ->and($data[0])->toBeInstanceOf(LinkData::class)
        ->and($data[0]->id)->toBe(LinkFixtures::LINK_ID)
        ->and($data[0]->platform)->toBe('website')
        ->and($data[0]->url)->toBe(LinkFixtures::WEBSITE_URL)
        ->and($data[0]->position)->toBe(0);
});

it('numbers the links in steps, so a later insertion has room between them', function () {
    $this->links->shouldReceive('replaceForOwner')->once();

    $data = $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
        LinkFixtures::target(),
        LinkFixtures::target(LinkPlatform::TikTok, 'https://tiktok.com/@ada.salon'),
    ]))->value();

    expect(array_map(static fn (LinkData $link): int => $link->position, $data))->toBe([0, 10, 20]);
});

it('orders the links the way the caller listed them, not by platform', function () {
    $this->links->shouldReceive('replaceForOwner')->once();

    $data = $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(LinkPlatform::TikTok, 'https://tiktok.com/@ada.salon'),
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
    ]))->value();

    expect(array_map(static fn (LinkData $link): string => $link->platform, $data))
        ->toBe(['tiktok', 'website']);
});

it('mints one uuid per link, in the order it wrote them', function () {
    $this->links->shouldReceive('replaceForOwner')->once();

    $data = $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
        LinkFixtures::target(),
    ]))->value();

    expect(array_map(static fn (LinkData $link): string => $link->id, $data))
        ->toBe([LinkFixtures::LINK_ID, LinkFixtures::SECOND_LINK_ID]);
});

it('stamps every link with the injected clock', function () {
    $this->links->shouldReceive('replaceForOwner')->once()
        ->with(Mockery::any(), Mockery::any(), Mockery::on(
            fn (array $links): bool => array_reduce(
                $links,
                static fn (bool $carry, Link $link): bool => $carry && $link->createdAt == LinkFixtures::now(),
                true,
            ),
        ));

    $this->useCase->handle(replaceLinksInput([LinkFixtures::target()]));
});

it('scopes every link to the owner that asked, by uuid', function () {
    $this->links->shouldReceive('replaceForOwner')->once()
        ->with(
            LinkOwnerType::StaffMember,
            LinkFixtures::LINK_ID,
            Mockery::on(fn (array $links): bool => $links[0]->ownerId === LinkFixtures::LINK_ID
                && $links[0]->ownerType === LinkOwnerType::StaffMember),
        );

    $this->useCase->handle(replaceLinksInput(
        [LinkFixtures::target()],
        ownerType: LinkOwnerType::StaffMember,
        ownerId: LinkFixtures::LINK_ID,
    ));
});

it('clears the list when the caller sends no link at all', function () {
    $this->links->shouldReceive('replaceForOwner')->once()
        ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, []);

    $response = $this->useCase->handle(replaceLinksInput());

    expect($response)->toBeInstanceOf(UseCaseResponse::class)
        ->and($response->succeeded())->toBeTrue()
        ->and($response->value())->toBe([]);
});

it('hands back a plain list, never an entity', function () {
    $this->links->shouldReceive('replaceForOwner')->once();

    $data = $this->useCase->handle(replaceLinksInput([
        LinkFixtures::target(),
        LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
    ]))->value();

    expect(array_keys($data))->toBe([0, 1])
        ->and($data)->each->toBeInstanceOf(LinkData::class);
});

describe('a list the same platform appears in twice', function () {
    it('refuses it as a conflict the caller can act on', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        $response = $this->useCase->handle(replaceLinksInput([
            LinkFixtures::target(),
            LinkFixtures::target(LinkPlatform::Instagram, 'https://instagram.com/ada.salon.mx'),
        ]));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('duplicate_link_platform')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('refuses it even when the repetition is not adjacent', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        $response = $this->useCase->handle(replaceLinksInput([
            LinkFixtures::target(),
            LinkFixtures::target(LinkPlatform::Website, LinkFixtures::WEBSITE_URL),
            LinkFixtures::target(LinkPlatform::Instagram, 'https://instagram.com/otra'),
        ]));

        expect($response->error()->code)->toBe('duplicate_link_platform');
    });

    it('names the platform that was repeated', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        $response = $this->useCase->handle(replaceLinksInput([
            LinkFixtures::target(),
            LinkFixtures::target(),
        ]));

        expect($response->error()->cause()?->getMessage())
            ->toBe('Platform [instagram] appears more than once for the same owner.');
    });

    it('refuses before it mints a single uuid', function () {
        $ids = Mockery::mock(IdGenerator::class);
        $ids->shouldNotReceive('next');
        $this->links->shouldNotReceive('replaceForOwner');

        $useCase = new ReplaceLinks($this->links, $ids, new FakeClock(LinkFixtures::now()));

        expect($useCase->handle(replaceLinksInput([LinkFixtures::target(), LinkFixtures::target()]))->failed())
            ->toBeTrue();
    });
});

it('lets a storage failure escape rather than dressing it as a refusal', function () {
    $this->links->shouldReceive('replaceForOwner')->once()
        ->andThrow(new RuntimeException('SQLSTATE[23505] duplicate key value'));

    expect(fn () => $this->useCase->handle(replaceLinksInput([LinkFixtures::target()])))
        ->toThrow(RuntimeException::class);
});
