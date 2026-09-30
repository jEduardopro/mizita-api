<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\InvalidServiceSearch;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\ValueObjects\ServiceQuery;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class ListServicesInput
{
    public const MAXIMUM_SEARCH_LENGTH = 120;

    public const MAXIMUM_STAFF_FILTER_SIZE = Service::MAXIMUM_STAFF_MEMBERS;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    private const DEFAULT_SORT = ServiceSort::Name;

    private const DEFAULT_DIRECTION = SortDirection::Ascending;

    private const IGNORED_SEARCH_WORDS = [
        'min', 'mins', 'minute', 'minutes', 'minuto', 'minutos',
        'h', 'hr', 'hrs', 'hora', 'horas',
        'eur', 'usd', 'mxn',
    ];

    /**
     * @param  list<string>  $staffIds
     */
    public function __construct(
        public ?string $search,
        public ?string $sort,
        public ?string $direction,
        public ?int $page,
        public ?int $perPage,
        public array $staffIds = [],
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
            staffIds: self::identifiers($payload['staff_ids'] ?? null),
        );
    }

    /**
     * @throws InvalidServiceSearch
     * @throws UnknownStaffMember
     */
    public function validate(): void
    {
        $this->validateSearch();
        $this->validateStaffIds();
    }

    public function toQuery(): ServiceQuery
    {
        return new ServiceQuery(
            search: SearchTerm::of($this->search, self::IGNORED_SEARCH_WORDS),
            sort: ServiceSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
            direction: SortDirection::tryFrom((string) $this->direction) ?? self::DEFAULT_DIRECTION,
            pagination: Pagination::of($this->page, $this->perPage),
            staffIds: $this->staffIds,
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

    /**
     * @return list<string>
     */
    private static function identifiers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $identifier): string => is_string($identifier) ? $identifier : '',
            $value,
        ));
    }

    private function validateSearch(): void
    {
        if ($this->search !== null && mb_strlen(trim($this->search)) > self::MAXIMUM_SEARCH_LENGTH) {
            throw InvalidServiceSearch::tooLong(self::MAXIMUM_SEARCH_LENGTH);
        }
    }

    private function validateStaffIds(): void
    {
        $this->validateStaffFilterSize();
        $this->validateStaffIdShapes();
        $this->validateStaffIdsAreDistinct();
    }

    private function validateStaffFilterSize(): void
    {
        if (count($this->staffIds) > self::MAXIMUM_STAFF_FILTER_SIZE) {
            throw UnknownStaffMember::amongFilter();
        }
    }

    private function validateStaffIdShapes(): void
    {
        foreach ($this->staffIds as $staffId) {
            if (preg_match(self::UUID_PATTERN, $staffId) !== 1) {
                throw UnknownStaffMember::amongFilter();
            }
        }
    }

    private function validateStaffIdsAreDistinct(): void
    {
        if (count(array_unique($this->staffIds)) !== count($this->staffIds)) {
            throw UnknownStaffMember::amongFilter();
        }
    }
}
