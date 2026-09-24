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
