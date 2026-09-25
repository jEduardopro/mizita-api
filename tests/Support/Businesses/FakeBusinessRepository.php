<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use DateTimeImmutable;
use Throwable;

final class FakeBusinessRepository implements BusinessRepository
{
    /**
     * @var array<string, Business>
     */
    private array $businesses = [];

    /**
     * @var list<string>
     */
    private array $takenNames = [];

    /**
     * @var list<string>
     */
    private array $takenSlugs = [];

    private ?Throwable $saveFailure = null;

    /**
     * @var list<Business>
     */
    public array $saved = [];

    /**
     * @var list<string>
     */
    public array $idsRead = [];

    /**
     * @var list<string>
     */
    public array $nameChecks = [];

    /**
     * @var list<string>
     */
    public array $slugChecks = [];

    /**
     * @var list<string>
     */
    public array $slugsRead = [];

    /**
     * @var list<string>
     */
    public array $deleted = [];

    /**
     * @var list<string>
     */
    public array $closedIdsRead = [];

    /**
     * @var list<string>
     */
    public array $closedOwnersRead = [];

    /**
     * @var list<DateTimeImmutable>
     */
    public array $purgeCutoffs = [];

    public function store(Business ...$businesses): self
    {
        foreach ($businesses as $business) {
            $this->businesses[$business->id] = $business;
        }

        return $this;
    }

    public function withTakenNames(string ...$names): self
    {
        $this->takenNames = array_map(mb_strtolower(...), $names);

        return $this;
    }

    public function withTakenSlugs(string ...$slugs): self
    {
        $this->takenSlugs = $slugs;

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    public function findById(string $id): Business
    {
        $this->idsRead[] = $id;

        return $this->open($id) ?? throw BusinessNotFound::withId($id);
    }

    public function findBySlug(string $slug): Business
    {
        $this->slugsRead[] = $slug;

        return $this->matching($slug) ?? throw BusinessNotFound::withSlug($slug);
    }

    public function existsBySlug(string $slug): bool
    {
        $this->slugsRead[] = $slug;

        return $this->matching($slug) !== null
            || in_array(self::fold($slug), array_map(self::fold(...), $this->takenSlugs), true);
    }

    /**
     * @param  list<string>  $ids
     * @return list<Business>
     */
    public function findManyByIds(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            $this->idsRead[] = $id;

            $business = $this->open($id);

            if ($business !== null) {
                $found[] = $business;
            }
        }

        return $found;
    }

    public function findClosedById(string $id): ?Business
    {
        $this->closedIdsRead[] = $id;

        $business = $this->businesses[$id] ?? null;

        return $business !== null && $business->isClosed() ? $business : null;
    }

    public function findClosedOwnedBy(string $accountId): ?Business
    {
        $this->closedOwnersRead[] = $accountId;

        $closedByAccount = array_filter(
            $this->businesses,
            static fn (Business $business): bool => $business->isClosed()
                && $business->closedByAccountId() === $accountId,
        );

        usort(
            $closedByAccount,
            static fn (Business $earlier, Business $later): int => $later->closedAt() <=> $earlier->closedAt(),
        );

        return $closedByAccount[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function idsDueForPurge(DateTimeImmutable $cutoff): array
    {
        $this->purgeCutoffs[] = $cutoff;

        $due = array_filter(
            $this->businesses,
            static fn (Business $business): bool => $business->isClosed()
                && ! $business->isPurged()
                && $business->closedAt() <= $cutoff,
        );

        usort(
            $due,
            static fn (Business $earlier, Business $later): int => $earlier->closedAt() <=> $later->closedAt(),
        );

        return array_map(static fn (Business $business): string => $business->id, $due);
    }

    public function existsByName(string $name): bool
    {
        $this->nameChecks[] = $name;

        return in_array(mb_strtolower($name), $this->takenNames, true);
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $base): array
    {
        $this->slugChecks[] = $base;

        return array_values(array_filter(
            $this->takenSlugs,
            static fn (string $slug): bool => str_starts_with($slug, $base),
        ));
    }

    public function save(Business $business): void
    {
        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->businesses[$business->id] = $business;
        $this->saved[] = $business;
    }

    public function delete(string $id): void
    {
        $this->deleted[] = $id;

        unset($this->businesses[$id]);
    }

    private function matching(string $slug): ?Business
    {
        foreach ($this->businesses as $business) {
            if (! $business->isClosed() && self::fold($business->slug()) === self::fold($slug)) {
                return $business;
            }
        }

        return null;
    }

    private function open(string $id): ?Business
    {
        $business = $this->businesses[$id] ?? null;

        return $business !== null && ! $business->isClosed() ? $business : null;
    }

    private static function fold(string $slug): string
    {
        return mb_strtolower(trim($slug));
    }
}
