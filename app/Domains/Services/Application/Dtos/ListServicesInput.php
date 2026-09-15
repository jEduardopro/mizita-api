<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Exceptions\InvalidServiceSearch;
use App\Domains\Services\ValueObjects\ServiceQuery;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

final readonly class ListServicesInput
{
    public const MAXIMUM_SEARCH_LENGTH = 120;

    private const DEFAULT_SORT = ServiceSort::Name;

    private const DEFAULT_DIRECTION = SortDirection::Ascending;

    public function __construct(
        public ?string $search,
        public ?string $sort,
        public ?string $direction,
        public ?int $page,
        public ?int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            search: self::textOrNull($payload['search'] ?? null),
            sort: self::textOrNull($payload['sort'] ?? null),
            direction: self::textOrNull($payload['direction'] ?? null),
            page: self::countOrNull($payload['page'] ?? null),
            perPage: self::countOrNull($payload['per_page'] ?? null),
        );
    }

    /**
     * @throws InvalidServiceSearch
     */
    public function validate(): void
    {
        $this->validateSearch();
    }

    public function toQuery(): ServiceQuery
    {
        return new ServiceQuery(
            search: $this->search === null || trim($this->search) === '' ? null : trim($this->search),
            sort: ServiceSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
            direction: SortDirection::tryFrom((string) $this->direction) ?? self::DEFAULT_DIRECTION,
            pagination: Pagination::of($this->page, $this->perPage),
        );
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function countOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function validateSearch(): void
    {
        if ($this->search !== null && mb_strlen(trim($this->search)) > self::MAXIMUM_SEARCH_LENGTH) {
            throw InvalidServiceSearch::tooLong(self::MAXIMUM_SEARCH_LENGTH);
        }
    }
}
