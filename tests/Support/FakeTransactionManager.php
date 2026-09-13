<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\TransactionManager;
use Throwable;

/**
 * A TransactionManager that simply invokes the unit of work, and remembers
 * that it did.
 *
 * Three things a test needs from it: how many times a transaction was opened -
 * zero is the assertion on every early-return path - through isRunning(),
 * whether a given write happened inside one, and through failAtCommit(), a
 * commit that fails after every write in the closure succeeded.
 *
 * A failure inside the unit of work propagates, exactly as DB::transaction()
 * rolls back and rethrows. A fake that swallowed it would quietly turn every
 * rollback assertion green for the wrong reason.
 */
final class FakeTransactionManager implements TransactionManager
{
    private int $runs = 0;

    private bool $running = false;

    private ?Throwable $commitFailure = null;

    /**
     * Makes the commit itself fail, with the closure having run to completion
     * first.
     *
     * A distinct case from the work throwing, and the one that matters for
     * anything announcing events afterwards: the unit of work succeeded, every
     * write was made, the outcome was returned - and then none of it survived.
     * A deferred constraint firing at commit, or a connection lost between the
     * last write and COMMIT, both look exactly like this.
     */
    public function failAtCommit(Throwable $failure): void
    {
        $this->commitFailure = $failure;
    }

    public function run(callable $work): mixed
    {
        $this->runs++;
        $this->running = true;

        try {
            $outcome = $work();

            if ($this->commitFailure !== null) {
                throw $this->commitFailure;
            }

            return $outcome;
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
