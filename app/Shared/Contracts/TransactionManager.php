<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/** Keeps DB:: out of use cases, so a test can substitute one that just invokes the callable. */
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
