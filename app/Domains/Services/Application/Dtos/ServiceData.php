<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use DateTimeImmutable;

final readonly class ServiceData
{
    /**
     * @param  list<StaffMemberSnapshot>  $staff
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public int $durationMinutes,
        public int $bufferMinutes,
        public string $price,
        public ServiceColor $color,
        public bool $active,
        public ?string $imageUrl,
        public string $bookingUrl,
        public array $staff,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  list<StaffMemberSnapshot>  $staff
     */
    public static function fromEntity(
        Service $service,
        array $staff,
        ?string $imageUrl,
        string $bookingUrl,
    ): self {
        return new self(
            id: $service->id,
            name: $service->name(),
            slug: $service->slug(),
            description: $service->description(),
            durationMinutes: $service->durationMinutes(),
            bufferMinutes: $service->bufferMinutes(),
            price: $service->price(),
            color: $service->color(),
            active: $service->isActive(),
            imageUrl: $imageUrl,
            bookingUrl: $bookingUrl,
            staff: $staff,
            createdAt: $service->createdAt,
        );
    }
}
