<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class CustomerQuery
{
    /**
     * @param  list<string>  $phoneMatches  customer uuids whose phone matches the search
     */
    public function __construct(
        public ?SearchTerm $search,
        public CustomerSort $sort,
        public SortDirection $direction,
        public Pagination $pagination,
        public array $phoneMatches = [],
    ) {}
}
