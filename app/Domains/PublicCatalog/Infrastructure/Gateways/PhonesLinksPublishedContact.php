<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicLink;

final class PhonesLinksPublishedContact implements PublishedContact
{
    public function __construct(
        private readonly PhoneRepository $phones,
        private readonly LinkRepository $links,
    ) {}

    public function forBusiness(string $businessId): PublicContact
    {
        return new PublicContact(
            phone: $this->phones->findForOwner(PhoneOwnerType::Business, $businessId)?->number()->e164(),
            links: array_map(
                static fn (Link $link): PublicLink => new PublicLink(
                    platform: $link->platform->value,
                    url: $link->url()->value,
                ),
                $this->links->allForOwner(LinkOwnerType::Business, $businessId),
            ),
        );
    }
}
