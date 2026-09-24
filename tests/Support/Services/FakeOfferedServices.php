<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\OfferedServices;
use App\Domains\Services\Entities\Service;

final class FakeOfferedServices implements OfferedServices
{
    /**
     * @var list<Service>
     */
    private array $services = [];

    /**
     * @var list<array{businessId: string, staffId: string}>
     */
    public array $calls = [];

    public function store(Service ...$services): self
    {
        $this->services = [...$this->services, ...array_values($services)];

        return $this;
    }

    /**
     * @return list<Service>
     */
    public function offeredBy(string $businessId, string $staffId): array
    {
        $this->calls[] = ['businessId' => $businessId, 'staffId' => $staffId];

        return array_values(array_filter(
            $this->services,
            static fn (Service $service): bool => $service->businessId === $businessId
                && in_array($staffId, $service->staffIds(), true),
        ));
    }
}
