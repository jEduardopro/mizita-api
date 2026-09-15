<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Application\Dtos\CreateServiceInput;
use App\Domains\Services\Application\Dtos\UpdateServiceInput;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class ServiceFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const SERVICE_ID = '01930000-0000-7000-8000-0000000000e1';

    public const SECOND_SERVICE_ID = '01930000-0000-7000-8000-0000000000e2';

    public const THIRD_SERVICE_ID = '01930000-0000-7000-8000-0000000000e3';

    public const GENERATED_SERVICE_ID = '01930000-0000-7000-8000-0000000000e9';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const STAFF_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_STAFF_ID = '01930000-0000-7000-8000-0000000000d2';

    public const FOREIGN_STAFF_ID = '01930000-0000-7000-8000-0000000000d9';

    public const NAME = 'Corte de pelo';

    public const SLUG = 'corte-de-pelo';

    public const BUSINESS_SLUG = 'ada-salon';

    public const BASE_URL = 'https://mizita.test';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * @param  list<string>  $staffIds
     */
    public static function service(
        string $id = self::SERVICE_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        string $name = self::NAME,
        string $slug = self::SLUG,
        ?string $description = 'Incluye lavado.',
        int $durationMinutes = 45,
        int $bufferMinutes = 10,
        string $price = '250.00',
        ServiceColor $color = ServiceColor::Teal,
        bool $active = true,
        array $staffIds = [self::STAFF_ID],
        ?DateTimeImmutable $createdAt = null,
    ): Service {
        return Service::restore(
            id: $id,
            businessId: $businessId,
            name: $name,
            slug: Slug::restore($slug),
            description: $description,
            duration: Duration::restore($durationMinutes),
            buffer: Buffer::restore($bufferMinutes),
            price: Price::restore($price),
            color: $color,
            active: $active,
            staffIds: $staffIds,
            createdAt: $createdAt ?? self::now(),
        );
    }

    /**
     * @param  list<string>  $staffIds
     */
    public static function createInput(
        string $name = self::NAME,
        ?string $description = 'Incluye lavado.',
        int $durationMinutes = 45,
        int $bufferMinutes = 10,
        string $price = '250.00',
        string $color = 'teal',
        bool $active = true,
        array $staffIds = [self::STAFF_ID],
    ): CreateServiceInput {
        return new CreateServiceInput(
            name: $name,
            description: $description,
            durationMinutes: $durationMinutes,
            bufferMinutes: $bufferMinutes,
            price: $price,
            color: $color,
            active: $active,
            staffIds: $staffIds,
        );
    }

    /**
     * @param  list<string>  $staffIds
     */
    public static function updateInput(
        string $serviceId = self::SERVICE_ID,
        string $name = self::NAME,
        ?string $description = 'Incluye lavado.',
        int $durationMinutes = 45,
        int $bufferMinutes = 10,
        string $price = '250.00',
        string $color = 'teal',
        bool $active = true,
        array $staffIds = [self::STAFF_ID],
    ): UpdateServiceInput {
        return new UpdateServiceInput(
            serviceId: $serviceId,
            name: $name,
            description: $description,
            durationMinutes: $durationMinutes,
            bufferMinutes: $bufferMinutes,
            price: $price,
            color: $color,
            active: $active,
            staffIds: $staffIds,
        );
    }
}
