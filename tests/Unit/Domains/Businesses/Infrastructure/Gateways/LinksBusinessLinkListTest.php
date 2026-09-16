<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\Infrastructure\Gateways\LinksBusinessLinkList;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Links\Application\UseCases\ReplaceLinks;
use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\Exceptions\DuplicateLinkPlatform;
use App\Domains\Links\Exceptions\InvalidLinkPlatform;
use App\Domains\Links\Exceptions\InvalidLinkUrl;
use App\Domains\Links\Exceptions\LinkPlatformMismatch;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Links\LinkFixtures;

function businessLinkSnapshot(
    string $platform = 'instagram',
    string $url = LinkFixtures::INSTAGRAM_URL,
    int $position = 0,
): BusinessLinkSnapshot {
    return new BusinessLinkSnapshot(platform: $platform, url: $url, position: $position);
}

beforeEach(function () {
    $this->links = Mockery::mock(LinkRepository::class);

    $this->linkList = new LinksBusinessLinkList(
        $this->links,
        new ReplaceLinks(
            $this->links,
            new FixedIdGenerator(
                LinkFixtures::LINK_ID,
                LinkFixtures::SECOND_LINK_ID,
                LinkFixtures::THIRD_LINK_ID,
            ),
            new FakeClock(LinkFixtures::now()),
        ),
    );

    $this->read = fn (): array => $this->linkList->forBusiness(FakeBusinessContext::BUSINESS_ID);

    $this->replace = fn (BusinessLinkSnapshot ...$links): mixed => $this->linkList->replaceForBusiness(
        FakeBusinessContext::BUSINESS_ID,
        array_values($links),
    );
});

describe('reading the links on file', function () {
    it('answers with an empty list when the business published none', function () {
        $this->links->shouldReceive('allForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID)
            ->andReturn([]);

        expect(($this->read)())->toBe([]);
    });

    it('translates each stored link into the snapshot the business domain reads', function () {
        $this->links->shouldReceive('allForOwner')->once()->andReturn([
            LinkFixtures::link(platform: LinkPlatform::Instagram, url: LinkFixtures::INSTAGRAM_URL, position: 0),
            LinkFixtures::link(
                id: LinkFixtures::SECOND_LINK_ID,
                platform: LinkPlatform::Website,
                url: LinkFixtures::WEBSITE_URL,
                position: 10,
            ),
        ]);

        $snapshots = ($this->read)();

        expect($snapshots)->toHaveCount(2)
            ->and($snapshots[0])->toBeInstanceOf(BusinessLinkSnapshot::class)
            ->and($snapshots[0]->platform)->toBe('instagram')
            ->and($snapshots[0]->url)->toBe(LinkFixtures::INSTAGRAM_URL)
            ->and($snapshots[0]->position)->toBe(0)
            ->and($snapshots[1]->platform)->toBe('website')
            ->and($snapshots[1]->url)->toBe(LinkFixtures::WEBSITE_URL)
            ->and($snapshots[1]->position)->toBe(10);
    });

    it('asks for the links under the business uuid, never an internal key', function () {
        $ownerId = null;

        $this->links->shouldReceive('allForOwner')->once()
            ->with(LinkOwnerType::Business, Mockery::capture($ownerId))
            ->andReturn([]);

        ($this->read)();

        expect($ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->toBeString()
            ->and(is_numeric($ownerId))->toBeFalse();
    });

    it('hands back the position the row stores, whatever it is', function () {
        $this->links->shouldReceive('allForOwner')->once()
            ->andReturn([LinkFixtures::link(position: 250)]);

        expect(($this->read)()[0]->position)->toBe(250);
    });
});

describe('replacing the links a business submitted', function () {
    it('replaces the whole set under the business uuid, as business links', function () {
        $ownerType = null;
        $ownerId = null;
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()->with(
            Mockery::capture($ownerType),
            Mockery::capture($ownerId),
            Mockery::capture($saved),
        );

        ($this->replace)(businessLinkSnapshot());

        expect($ownerType)->toBe(LinkOwnerType::Business)
            ->and($ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($saved)->toHaveCount(1)
            ->and($saved[0])->toBeInstanceOf(Link::class)
            ->and($saved[0]->id)->toBe(LinkFixtures::LINK_ID)
            ->and($saved[0]->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($saved[0]->platform)->toBe(LinkPlatform::Instagram)
            ->and($saved[0]->url()->value)->toBe(LinkFixtures::INSTAGRAM_URL)
            ->and($saved[0]->createdAt)->toEqual(LinkFixtures::now());
    });

    it('clears every link when the business submits an empty list', function () {
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, Mockery::capture($saved));

        ($this->replace)();

        expect($saved)->toBe([]);
    });

    it('delegates the links in the order the snapshot positions ask for', function () {
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, Mockery::capture($saved));

        ($this->replace)(
            businessLinkSnapshot(platform: 'website', url: LinkFixtures::WEBSITE_URL, position: 30),
            businessLinkSnapshot(platform: 'instagram', url: LinkFixtures::INSTAGRAM_URL, position: 10),
            businessLinkSnapshot(platform: 'facebook', url: 'https://facebook.com/ada.salon', position: 20),
        );

        expect(array_map(static fn (Link $link): string => $link->platform->value, $saved))
            ->toBe(['instagram', 'facebook', 'website']);
    });

    it('treats the submitted position as an ordering hint and stores the recomputed one', function () {
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, Mockery::capture($saved));

        ($this->replace)(
            businessLinkSnapshot(platform: 'website', url: LinkFixtures::WEBSITE_URL, position: 30),
            businessLinkSnapshot(platform: 'instagram', url: LinkFixtures::INSTAGRAM_URL, position: 10),
            businessLinkSnapshot(platform: 'facebook', url: 'https://facebook.com/ada.salon', position: 20),
        );

        expect(array_map(static fn (Link $link): int => $link->position(), $saved))->toBe([0, 10, 20]);
    });

    it('keeps the order the list arrived in when every position ties', function () {
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, Mockery::capture($saved));

        ($this->replace)(
            businessLinkSnapshot(platform: 'website', url: LinkFixtures::WEBSITE_URL, position: 0),
            businessLinkSnapshot(platform: 'instagram', url: LinkFixtures::INSTAGRAM_URL, position: 0),
        );

        expect(array_map(static fn (Link $link): string => $link->platform->value, $saved))
            ->toBe(['website', 'instagram']);
    });

    it('accepts a platform however the client cased or padded it', function (string $platform) {
        $saved = null;

        $this->links->shouldReceive('replaceForOwner')->once()
            ->with(LinkOwnerType::Business, FakeBusinessContext::BUSINESS_ID, Mockery::capture($saved));

        ($this->replace)(businessLinkSnapshot(platform: $platform));

        expect($saved[0]->platform)->toBe(LinkPlatform::Instagram);
    })->with([
        'lower case' => 'instagram',
        'upper case' => 'INSTAGRAM',
        'padded' => '  Instagram  ',
    ]);
});

describe('refusing links the neighbour will not take', function () {
    it('refuses a platform the catalogue does not know, before touching the repository', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        expect(fn () => ($this->replace)(businessLinkSnapshot(platform: 'myspace')))
            ->toThrow(InvalidLinkPlatform::class);
    });

    it('refuses a url that does not belong to the platform it was filed under', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        expect(fn () => ($this->replace)(businessLinkSnapshot(url: 'https://example.com/ada')))
            ->toThrow(LinkPlatformMismatch::class);
    });

    it('refuses a url the domain cannot read', function (string $url) {
        $this->links->shouldNotReceive('replaceForOwner');

        expect(fn () => ($this->replace)(businessLinkSnapshot(platform: 'website', url: $url)))
            ->toThrow(InvalidLinkUrl::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'no scheme' => 'mizita.test/ada-salon',
        'unsupported scheme' => 'javascript:alert(1)',
        'hostless' => 'https://localhost',
    ]);
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->links->shouldReceive('replaceForOwner')->once();

        expect(($this->replace)(businessLinkSnapshot()))->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(BusinessLinkList::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on the write the port exposes', function () {
        expect((string) (new ReflectionMethod(BusinessLinkList::class, 'replaceForBusiness'))->getReturnType())
            ->toBe('void');
    });

    it('rethrows the neighbour refusal itself, so the surrounding transaction rolls back', function () {
        $this->links->shouldNotReceive('replaceForOwner');

        try {
            ($this->replace)(
                businessLinkSnapshot(url: LinkFixtures::INSTAGRAM_URL),
                businessLinkSnapshot(url: 'https://instagram.com/ada.salon.mx'),
            );
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(DuplicateLinkPlatform::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('duplicate_link_platform')
            ->and($thrown->kind())->toBe(DomainFailureKind::Conflict);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the links table is gone');

        $this->links->shouldReceive('replaceForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->replace)(businessLinkSnapshot()))->toThrow($bug);
    });
});
