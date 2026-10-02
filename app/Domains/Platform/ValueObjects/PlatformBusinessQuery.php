<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class PlatformBusinessQuery
{
    public function __construct(
        public ?SearchTerm $search,
        public PlatformBusinessSort $sort,
        public SortDirection $direction,
        public Pagination $pagination,
    ) {}
}
