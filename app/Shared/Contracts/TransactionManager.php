<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Runs a unit of work atomically.
 *
 * Wrapping transactions behind a port keeps DB:: out of use cases, so a test
 * can substitute an implementation that simply invokes the callable.
 */
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
