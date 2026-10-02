<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\Exceptions\InvalidPlatformBusinessFilter;
use App\Domains\Platform\ValueObjects\PlatformBusinessQuery;
use App\Domains\Platform\ValueObjects\PlatformBusinessSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class ListPlatformBusinessesInput
{
    public const MAXIMUM_SEARCH_LENGTH = 120;

    public const FIRST_PAGE = 1;

    public const MINIMUM_PER_PAGE = 1;

    private const DEFAULT_SORT = PlatformBusinessSort::CreatedAt;

    private const DEFAULT_DIRECTION = SortDirection::Descending;

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
     * @throws InvalidPlatformBusinessFilter
     */
    public function validate(): void
    {
        $this->validateSearch();
        $this->validateSort();
        $this->validateDirection();
        $this->validatePage();
        $this->validatePerPage();
    }

    public function toQuery(): PlatformBusinessQuery
    {
        return new PlatformBusinessQuery(
            search: SearchTerm::of($this->search),
            sort: PlatformBusinessSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
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
            throw InvalidPlatformBusinessFilter::searchTooLong(self::MAXIMUM_SEARCH_LENGTH);
        }
    }

    private function validateSort(): void
    {
        if ($this->sort !== null && PlatformBusinessSort::tryFrom($this->sort) === null) {
            throw InvalidPlatformBusinessFilter::unknownSort($this->sort);
        }
    }

    private function validateDirection(): void
    {
        if ($this->direction !== null && SortDirection::tryFrom($this->direction) === null) {
            throw InvalidPlatformBusinessFilter::unknownDirection($this->direction);
        }
    }

    private function validatePage(): void
    {
        if ($this->page !== null && $this->page < self::FIRST_PAGE) {
            throw InvalidPlatformBusinessFilter::pageOutOfRange($this->page);
        }
    }

    private function validatePerPage(): void
    {
        if ($this->perPage === null) {
            return;
        }

        if ($this->perPage < self::MINIMUM_PER_PAGE || $this->perPage > Pagination::MAXIMUM_PER_PAGE) {
            throw InvalidPlatformBusinessFilter::perPageOutOfRange($this->perPage, Pagination::MAXIMUM_PER_PAGE);
        }
    }
}
