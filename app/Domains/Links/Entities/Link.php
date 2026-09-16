<?php

declare(strict_types=1);

namespace App\Domains\Links\Entities;

use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkUrl;
use DateTimeImmutable;

final class Link
{
    private function __construct(
        public readonly string $id,
        public readonly LinkOwnerType $ownerType,
        public readonly string $ownerId,
        public readonly LinkPlatform $platform,
        private LinkUrl $url,
        private int $position,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        LinkOwnerType $ownerType,
        string $ownerId,
        LinkPlatform $platform,
        LinkUrl $url,
        int $position,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            platform: $platform,
            url: $url,
            position: $position,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        LinkOwnerType $ownerType,
        string $ownerId,
        LinkPlatform $platform,
        LinkUrl $url,
        int $position,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            platform: $platform,
            url: $url,
            position: $position,
            createdAt: $createdAt,
        );
    }

    public function pointAt(LinkUrl $url): void
    {
        $this->url = $url;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function url(): LinkUrl
    {
        return $this->url;
    }

    public function position(): int
    {
        return $this->position;
    }
}
