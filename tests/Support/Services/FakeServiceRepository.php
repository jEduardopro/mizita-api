<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\ValueObjects\ServiceQuery;
use App\Shared\ValueObjects\Paginated;
use Throwable;

final class FakeServiceRepository implements ServiceRepository
{
    /**
     * @var array<string, Service>
     */
    private array $services = [];

    /**
     * @var array<string, Service>
     */
    private array $archived = [];

    /**
     * @var list<string>
     */
    private array $takenNames = [];

    /**
     * @var list<string>
     */
    private array $takenSlugs = [];

    /**
     * @var Paginated<Service>|null
     */
    private ?Paginated $page = null;

    private ?Throwable $saveFailure = null;

    /**
     * @var list<Service>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $deleted = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    /**
     * @var list<string>
     */
    public array $nameChecks = [];

    /**
     * @var list<string>
     */
    public array $slugLookups = [];

    /**
     * @var list<ServiceQuery>
     */
    public array $queries = [];

    public function store(Service ...$services): self
    {
        foreach ($services as $service) {
            $this->services[$this->keyFor($service->businessId, $service->id)] = $service;
        }

        return $this;
    }

    /**
     * @param  Paginated<Service>  $page
     */
    public function returning(Paginated $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function withTakenNames(string ...$names): self
    {
        $this->takenNames = [...$this->takenNames, ...array_values($names)];

        return $this;
    }

    public function withTakenSlugs(string ...$slugs): self
    {
        $this->takenSlugs = [...$this->takenSlugs, ...array_values($slugs)];

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    /**
     * @return Paginated<Service>
     */
    public function search(string $businessId, ServiceQuery $query): Paginated
    {
        $this->businessIdsSeen[] = $businessId;
        $this->queries[] = $query;

        return $this->page ?? Paginated::of([], 0, $query->pagination);
    }

    /**
     * @return list<Service>
     */
    public function activeForBusiness(string $businessId): array
    {
        $this->businessIdsSeen[] = $businessId;

        $active = array_values(array_filter(
            $this->services,
            static fn (Service $service): bool => $service->businessId === $businessId && $service->isActive(),
        ));

        usort($active, static fn (Service $one, Service $other): int => $one->name() <=> $other->name());

        return $active;
    }

    public function findForBusiness(string $businessId, string $id): Service
    {
        $this->businessIdsSeen[] = $businessId;

        return $this->services[$this->keyFor($businessId, $id)]
            ?? throw ServiceNotFound::withId($id);
    }

    public function findIncludingArchived(string $businessId, string $id): Service
    {
        $this->businessIdsSeen[] = $businessId;
        $key = $this->keyFor($businessId, $id);

        return $this->services[$key]
            ?? $this->archived[$key]
            ?? throw ServiceNotFound::withId($id);
    }

    public function existsByName(string $businessId, string $name): bool
    {
        $this->businessIdsSeen[] = $businessId;
        $this->nameChecks[] = $name;

        foreach ($this->takenNames as $taken) {
            if (mb_strtolower(trim($taken)) === mb_strtolower(trim($name))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array
    {
        $this->businessIdsSeen[] = $businessId;
        $this->slugLookups[] = $base;

        return array_values(array_filter(
            $this->takenSlugs,
            static fn (string $slug): bool => $slug === $base || str_starts_with($slug, $base.'-'),
        ));
    }

    /**
     * @return list<string>
     */
    public function namesMatching(string $businessId, string $base): array
    {
        $this->businessIdsSeen[] = $businessId;

        $folded = mb_strtolower($base);

        return array_values(array_filter(
            $this->takenNames,
            static fn (string $name): bool => mb_strtolower($name) === $folded
                || str_starts_with(mb_strtolower($name), $folded.' '),
        ));
    }

    public function save(Service $service): void
    {
        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->services[$this->keyFor($service->businessId, $service->id)] = $service;
        $this->saved[] = $service;
    }

    public function delete(string $businessId, string $id): void
    {
        $this->businessIdsSeen[] = $businessId;
        $key = $this->keyFor($businessId, $id);

        if (! isset($this->services[$key])) {
            throw ServiceNotFound::withId($id);
        }

        $this->archived[$key] = $this->services[$key];
        unset($this->services[$key]);

        $this->deleted[] = ['businessId' => $businessId, 'id' => $id];
    }

    private function keyFor(string $businessId, string $id): string
    {
        return $businessId.'|'.$id;
    }
}
