<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessMembership;

/**
 * A BusinessMembership answering from a list the test wrote.
 *
 * It lets a test state its precondition - "this caller belongs to nothing", or
 * "to these two businesses, in this order" - instead of arranging the rows that
 * would make the real query say so, which is a different subject from the one
 * under test whenever the membership itself is not what is being exercised.
 *
 * The order of the list is meaningful, as it is on the port: the head is the
 * business a caller who names none gets.
 */
final class FakeBusinessMembership implements BusinessMembership
{
    /**
     * @param  array<string, list<string>>  $businessIdsByAccount  account uuid => business uuids
     */
    public function __construct(
        private readonly array $businessIdsByAccount = [],
    ) {}

    /**
     * @return list<string>
     */
    public function businessIdsFor(string $accountId): array
    {
        return $this->businessIdsByAccount[$accountId] ?? [];
    }
}
