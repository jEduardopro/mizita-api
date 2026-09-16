<?php

declare(strict_types=1);

namespace App\Domains\Links\Application\UseCases;

use App\Domains\Links\Application\Dtos\LinkData;
use App\Domains\Links\Application\Dtos\ReplaceLinksInput;
use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\Exceptions\DuplicateLinkPlatform;
use App\Domains\Links\ValueObjects\LinkTarget;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ReplaceLinks
{
    private const POSITION_STEP = 10;

    public function __construct(
        private readonly LinkRepository $links,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<list<LinkData>>
     */
    public function handle(ReplaceLinksInput $input): UseCaseResponse
    {
        try {
            $this->refuseRepeatedPlatforms($input->targets);

            $links = $this->linksFrom($input);

            $this->links->replaceForOwner($input->ownerType, $input->ownerId, $links);

            return UseCaseResponse::success(array_map(
                static fn (Link $link): LinkData => LinkData::fromEntity($link),
                $links,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @param  list<LinkTarget>  $targets
     *
     * @throws DuplicateLinkPlatform
     */
    private function refuseRepeatedPlatforms(array $targets): void
    {
        $seen = [];

        foreach ($targets as $target) {
            if (in_array($target->platform->value, $seen, true)) {
                throw DuplicateLinkPlatform::for($target->platform->value);
            }

            $seen[] = $target->platform->value;
        }
    }

    /**
     * @return list<Link>
     */
    private function linksFrom(ReplaceLinksInput $input): array
    {
        $now = $this->clock->now();
        $links = [];

        foreach (array_values($input->targets) as $index => $target) {
            $links[] = Link::create(
                id: $this->ids->next(),
                ownerType: $input->ownerType,
                ownerId: $input->ownerId,
                platform: $target->platform,
                url: $target->url,
                position: $index * self::POSITION_STEP,
                now: $now,
            );
        }

        return $links;
    }
}
