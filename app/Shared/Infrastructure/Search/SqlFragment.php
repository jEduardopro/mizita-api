<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Search;

final readonly class SqlFragment
{
    /**
     * @param  list<mixed>  $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
    ) {}
}
