<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarAuthorizationStates;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;

final class FakeCalendarAuthorizationStates implements CalendarAuthorizationStates
{
    /**
     * @var array<string, PendingAuthorization>
     */
    private array $pending = [];

    /**
     * @var list<PendingAuthorization>
     */
    public array $issued = [];

    /**
     * @var list<string>
     */
    public array $consumed = [];

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
        private readonly string $nextState = IntegrationsFixtures::STATE,
    ) {}

    public function remember(string $state, PendingAuthorization $pending): self
    {
        $this->pending[$state] = $pending;

        return $this;
    }

    public function issue(PendingAuthorization $pending): string
    {
        $this->journal->record('states.issue');
        $this->issued[] = $pending;
        $this->pending[$this->nextState] = $pending;

        return $this->nextState;
    }

    public function consume(string $state): PendingAuthorization
    {
        $this->journal->record('states.consume');
        $this->consumed[] = $state;

        $pending = $this->pending[$state] ?? throw CalendarAuthorizationStateInvalid::expiredOrUsed();
        unset($this->pending[$state]);

        return $pending;
    }
}
