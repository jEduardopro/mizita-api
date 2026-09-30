<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

final readonly class CustomerTally
{
    public function __construct(
        public int $attended,
        public int $new,
    ) {}

    public function returning(): int
    {
        return $this->attended - $this->new;
    }
}
