<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

final readonly class ReportCriteria
{
    /**
     * @param  list<string>  $customerIds
     */
    public function __construct(
        public ?ReportWindow $window,
        public array $customerIds,
        public SortDirection $direction,
        public Pagination $pagination,
    ) {}
}
