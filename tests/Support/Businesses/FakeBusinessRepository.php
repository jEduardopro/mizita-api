<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
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

        return $this->businesses[$id] ?? throw BusinessNotFound::withId($id);
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

            if (isset($this->businesses[$id])) {
                $found[] = $this->businesses[$id];
            }
        }

        return $found;
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
            if (self::fold($business->slug()) === self::fold($slug)) {
                return $business;
            }
        }

        return null;
    }

    private static function fold(string $slug): string
    {
        return mb_strtolower(trim($slug));
    }
}
