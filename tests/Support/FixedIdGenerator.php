<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\IdGenerator;
use RuntimeException;

/**
 * An IdGenerator handing out a known queue of identifiers, in order.
 *
 * Knowing the ids up front is what lets a test assert the id on the output DTO
 * and the id on a dispatched event are the same value the use case generated,
 * instead of merely asserting "some uuid".
 */
final class FixedIdGenerator implements IdGenerator
{
    /** @var list<string> */
    private array $ids;

    public function __construct(string ...$ids)
    {
        $this->ids = $ids === [] ? ['01930000-0000-7000-8000-000000000001'] : array_values($ids);
    }

    public function next(): string
    {
        return array_shift($this->ids)
            ?? throw new RuntimeException('FixedIdGenerator ran out of ids: the code under test asked for more than the test supplied.');
    }
}
