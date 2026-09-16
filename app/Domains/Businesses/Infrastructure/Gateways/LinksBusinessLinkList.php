<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Links\Application\Dtos\ReplaceLinksInput;
use App\Domains\Links\Application\UseCases\ReplaceLinks;
use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkTarget;
use App\Domains\Links\ValueObjects\LinkUrl;

final class LinksBusinessLinkList implements BusinessLinkList
{
    public function __construct(
        private readonly LinkRepository $links,
        private readonly ReplaceLinks $replaceLinks,
    ) {}

    /**
     * @return list<BusinessLinkSnapshot>
     */
    public function forBusiness(string $businessId): array
    {
        return array_map(
            static fn (Link $link): BusinessLinkSnapshot => new BusinessLinkSnapshot(
                platform: $link->platform->value,
                url: $link->url()->value,
                position: $link->position(),
            ),
            $this->links->allForOwner(LinkOwnerType::Business, $businessId),
        );
    }

    /**
     * @param  list<BusinessLinkSnapshot>  $links
     */
    public function replaceForBusiness(string $businessId, array $links): void
    {
        $this->replaceLinks->handle(new ReplaceLinksInput(
            ownerType: LinkOwnerType::Business,
            ownerId: $businessId,
            targets: $this->targetsFrom($links),
        ))->value();
    }

    /**
     * @param  list<BusinessLinkSnapshot>  $links
     * @return list<LinkTarget>
     */
    private function targetsFrom(array $links): array
    {
        $ordered = $links;

        usort(
            $ordered,
            static fn (BusinessLinkSnapshot $one, BusinessLinkSnapshot $other): int => $one->position <=> $other->position,
        );

        return array_map(
            static fn (BusinessLinkSnapshot $link): LinkTarget => self::targetFrom($link),
            $ordered,
        );
    }

    private static function targetFrom(BusinessLinkSnapshot $link): LinkTarget
    {
        $platform = LinkPlatform::fromValue($link->platform);

        return new LinkTarget(
            platform: $platform,
            url: LinkUrl::forPlatform($platform, $link->url),
        );
    }
}
