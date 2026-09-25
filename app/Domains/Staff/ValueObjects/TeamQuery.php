<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class TeamQuery
{
    /**
     * @param  list<string>  $profileIdsMatchingPhone
     */
    public function __construct(
        public ?SearchTerm $search,
        public TeamSort $sort,
        public SortDirection $direction,
        public Pagination $pagination,
        public array $profileIdsMatchingPhone = [],
    ) {}
}
