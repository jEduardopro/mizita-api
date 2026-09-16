<?php

declare(strict_types=1);

namespace Tests\Support\Links;

use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkTarget;
use App\Domains\Links\ValueObjects\LinkUrl;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class LinkFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const LINK_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SECOND_LINK_ID = '01930000-0000-7000-8000-0000000000c2';

    public const THIRD_LINK_ID = '01930000-0000-7000-8000-0000000000c3';

    public const OWNER_KEY = 42;

    public const WEBSITE_URL = 'https://mizita.test/ada-salon';

    public const INSTAGRAM_URL = 'https://instagram.com/ada.salon';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function link(
        string $id = self::LINK_ID,
        LinkOwnerType $ownerType = LinkOwnerType::Business,
        string $ownerId = FakeBusinessContext::BUSINESS_ID,
        LinkPlatform $platform = LinkPlatform::Instagram,
        string $url = self::INSTAGRAM_URL,
        int $position = 0,
        ?DateTimeImmutable $createdAt = null,
    ): Link {
        return Link::restore(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            platform: $platform,
            url: LinkUrl::restore($url),
            position: $position,
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function target(
        LinkPlatform $platform = LinkPlatform::Instagram,
        string $url = self::INSTAGRAM_URL,
    ): LinkTarget {
        return new LinkTarget($platform, LinkUrl::restore($url));
    }
}
