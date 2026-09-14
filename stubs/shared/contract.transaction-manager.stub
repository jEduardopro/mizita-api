<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface TransactionManager
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $work
     * @return TReturn
     */
    public function run(callable $work): mixed;
}
