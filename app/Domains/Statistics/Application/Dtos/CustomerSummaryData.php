<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

use App\Domains\Statistics\ValueObjects\CustomerTally;

final readonly class CustomerSummaryData
{
    public function __construct(
        public int $attended,
        public int $new,
        public int $returning,
    ) {}

    public static function fromTally(CustomerTally $tally): self
    {
        return new self($tally->attended, $tally->new, $tally->returning());
    }
}
