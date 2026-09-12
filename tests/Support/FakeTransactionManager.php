<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\TransactionManager;

/**
 * A TransactionManager that simply invokes the unit of work, and remembers
 * that it did.
 *
 * Two things a test needs from it: how many times a transaction was opened -
 * zero is the assertion on every early-return path - and, through isRunning(),
 * whether a given write happened inside one.
 *
 * A failure inside the unit of work propagates, exactly as DB::transaction()
 * rolls back and rethrows. A fake that swallowed it would quietly turn every
 * rollback assertion green for the wrong reason.
 */
final class FakeTransactionManager implements TransactionManager
{
    private int $runs = 0;

    private bool $running = false;

    public function run(callable $work): mixed
    {
        $this->runs++;
        $this->running = true;

        try {
            return $work();
        } finally {
            $this->running = false;
        }
    }

    public function runs(): int
    {
        return $this->runs;
    }

    /**
     * True while a unit of work is in flight, so a repository double can record
     * whether it was called from inside the transaction.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}
