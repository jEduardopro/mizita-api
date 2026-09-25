<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidTeamSearch;
use App\Domains\Staff\ValueObjects\TeamQuery;
use App\Domains\Staff\ValueObjects\TeamSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class ListTeamMembersInput
{
    public const MAXIMUM_SEARCH_LENGTH = 120;

    private const DEFAULT_SORT = TeamSort::Name;

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
     * @throws InvalidTeamSearch
     */
    public function validate(): void
    {
        $this->validateSearch();
    }

    /**
     * @param  list<string>  $profileIdsMatchingPhone
     */
    public function toQuery(array $profileIdsMatchingPhone = []): TeamQuery
    {
        return new TeamQuery(
            search: SearchTerm::of($this->search),
            sort: TeamSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
            direction: SortDirection::tryFrom((string) $this->direction) ?? self::DEFAULT_DIRECTION,
            pagination: Pagination::of($this->page, $this->perPage),
            profileIdsMatchingPhone: $profileIdsMatchingPhone,
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
            throw InvalidTeamSearch::tooLong(self::MAXIMUM_SEARCH_LENGTH);
        }
    }
}
