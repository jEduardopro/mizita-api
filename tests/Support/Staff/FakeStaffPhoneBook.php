<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Shared\ValueObjects\PhoneNumber;

final class FakeStaffPhoneBook implements StaffPhoneBook
{
    /**
     * @var array<string, PhoneNumber>
     */
    private array $numbers = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    /**
     * @var list<list<string>>
     */
    public array $batchReads = [];

    /**
     * @var array<string, list<string>>
     */
    private array $matches = [];

    /**
     * @var list<string>
     */
    public array $numberSearches = [];

    /**
     * @var list<array{profileId: string, phone: ?PhoneNumber}>
     */
    public array $replacements = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function store(string $profileId, PhoneNumber $number): self
    {
        $this->numbers[$profileId] = $number;

        return $this;
    }

    public function matching(string $fragment, string ...$profileIds): self
    {
        $this->matches[$fragment] = array_values($profileIds);

        return $this;
    }

    /**
     * @param  list<string>  $profileIds
     * @return array<string, PhoneNumber>
     */
    public function forProfiles(array $profileIds): array
    {
        $this->batchReads[] = array_values($profileIds);

        return array_intersect_key($this->numbers, array_flip($profileIds));
    }

    /**
     * @return list<string>
     */
    public function profileIdsMatchingNumber(string $fragment): array
    {
        $this->numberSearches[] = $fragment;

        return $this->matches[$fragment] ?? [];
    }

    public function forProfile(string $profileId): ?PhoneNumber
    {
        $this->reads[] = $profileId;

        return $this->numbers[$profileId] ?? null;
    }

    public function replaceForProfile(string $profileId, ?PhoneNumber $phone): void
    {
        $this->journal->record('phones.replace');
        $this->replacements[] = ['profileId' => $profileId, 'phone' => $phone];

        if ($phone === null) {
            unset($this->numbers[$profileId]);

            return;
        }

        $this->numbers[$profileId] = $phone;
    }
}
