<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class ServiceQuery
{
    /**
     * @param  list<string>  $staffIds
     */
    public function __construct(
        public ?SearchTerm $search,
        public ServiceSort $sort,
        public SortDirection $direction,
        public Pagination $pagination,
        public array $staffIds = [],
    ) {}
}
