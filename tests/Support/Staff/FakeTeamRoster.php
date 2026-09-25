<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamRoster;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\TeamQuery;
use App\Shared\ValueObjects\Paginated;

final class FakeTeamRoster implements TeamRoster
{
    /**
     * @var list<StaffMember>
     */
    private array $members = [];

    /**
     * @var array<string, list<string>>
     */
    private array $emailsOnTeam = [];

    /**
     * @var list<array{businessId: string, query: TeamQuery}>
     */
    public array $searches = [];

    /**
     * @var list<array{businessId: string, emails: list<string>}>
     */
    public array $emailChecks = [];

    /**
     * @var list<string>
     */
    public array $teamLookups = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function store(StaffMember ...$members): self
    {
        foreach ($members as $member) {
            $this->members[] = $member;
        }

        return $this;
    }

    public function withEmailsOnTeam(string $businessId, string ...$emails): self
    {
        $this->emailsOnTeam[$businessId] = array_values($emails);

        return $this;
    }

    /**
     * @return Paginated<StaffMember>
     */
    public function search(string $businessId, TeamQuery $query): Paginated
    {
        $this->searches[] = ['businessId' => $businessId, 'query' => $query];

        $matching = array_values(array_filter(
            $this->members,
            static fn (StaffMember $member): bool => $member->businessId === $businessId,
        ));

        return Paginated::of(
            array_slice($matching, $query->pagination->offset(), $query->pagination->perPage),
            count($matching),
            $query->pagination,
        );
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function emailsAlreadyOnTeam(string $businessId, array $emails): array
    {
        $this->journal->record('roster.emails');
        $this->emailChecks[] = ['businessId' => $businessId, 'emails' => array_values($emails)];

        return array_values(array_intersect($emails, $this->emailsOnTeam[$businessId] ?? []));
    }

    /**
     * @return list<string>
     */
    public function accountIdsOnTeam(string $businessId): array
    {
        $this->teamLookups[] = $businessId;

        return array_values(array_unique(array_map(
            static fn (StaffMember $member): string => $member->accountId,
            array_filter(
                $this->members,
                static fn (StaffMember $member): bool => $member->businessId === $businessId,
            ),
        )));
    }
}
