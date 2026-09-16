<?php

declare(strict_types=1);

use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkUrl;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Links\LinkFixtures;

function createLink(
    LinkPlatform $platform = LinkPlatform::Instagram,
    string $url = LinkFixtures::INSTAGRAM_URL,
    int $position = 0,
    LinkOwnerType $ownerType = LinkOwnerType::Business,
    string $ownerId = FakeBusinessContext::BUSINESS_ID,
): Link {
    return Link::create(
        id: LinkFixtures::LINK_ID,
        ownerType: $ownerType,
        ownerId: $ownerId,
        platform: $platform,
        url: LinkUrl::restore($url),
        position: $position,
        now: LinkFixtures::now(),
    );
}

describe('creating a link', function () {
    it('holds every fact it was given, pinned to the owner that asked', function () {
        $link = createLink(position: 20);

        expect($link->id)->toBe(LinkFixtures::LINK_ID)
            ->and($link->ownerType)->toBe(LinkOwnerType::Business)
            ->and($link->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($link->platform)->toBe(LinkPlatform::Instagram)
            ->and($link->url()->value)->toBe(LinkFixtures::INSTAGRAM_URL)
            ->and($link->position())->toBe(20)
            ->and($link->createdAt)->toEqual(LinkFixtures::now());
    });

    it('names its owner by the uuid, never by a row number', function () {
        expect(createLink()->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->not->toBe((string) LinkFixtures::OWNER_KEY);
    });

    it('belongs to a staff member just as readily as to a business', function () {
        expect(createLink(ownerType: LinkOwnerType::StaffMember)->ownerType)->toBe(LinkOwnerType::StaffMember);
    });

    it('accepts any platform the enum names', function (LinkPlatform $platform) {
        expect(createLink(platform: $platform)->platform)->toBe($platform);
    })->with(fn () => LinkPlatform::cases());
});

describe('restoring a link from persistence', function () {
    it('keeps the stored url and position as the columns hold them', function () {
        $link = LinkFixtures::link(url: 'https://instagram.com/ada.salon?utm=1', position: 30);

        expect($link->url()->value)->toBe('https://instagram.com/ada.salon?utm=1')
            ->and($link->position())->toBe(30);
    });
});

describe('changing where a link points', function () {
    it('takes the new url and keeps everything else', function () {
        $link = createLink();

        $link->pointAt(LinkUrl::restore('https://instagram.com/ada.salon.mx'));

        expect($link->url()->value)->toBe('https://instagram.com/ada.salon.mx')
            ->and($link->id)->toBe(LinkFixtures::LINK_ID)
            ->and($link->platform)->toBe(LinkPlatform::Instagram)
            ->and($link->position())->toBe(0)
            ->and($link->createdAt)->toEqual(LinkFixtures::now());
    });

    it('cannot be talked into changing the platform it was created for', function () {
        expect((new ReflectionProperty(Link::class, 'platform'))->isReadOnly())->toBeTrue()
            ->and(method_exists(Link::class, 'setPlatform'))->toBeFalse();
    });
});

describe('reordering a link', function () {
    it('takes the new position and keeps the url', function () {
        $link = createLink();

        $link->moveTo(40);

        expect($link->position())->toBe(40)
            ->and($link->url()->value)->toBe(LinkFixtures::INSTAGRAM_URL);
    });

    it('accepts the first position, because the list is zero based', function () {
        $link = createLink(position: 10);

        $link->moveTo(0);

        expect($link->position())->toBe(0);
    });
});
