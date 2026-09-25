<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;

final class FakeBusinessProfiles implements BusinessProfiles
{
    /**
     * @var array<string, BusinessCalendarProfile>
     */
    private array $profiles = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
    ) {}

    public function add(string $businessId, BusinessCalendarProfile $profile): self
    {
        $this->profiles[$businessId] = $profile;

        return $this;
    }

    public function profileOf(string $businessId): BusinessCalendarProfile
    {
        $this->journal->record('businesses.profileOf');
        $this->lookups[] = $businessId;

        return $this->profiles[$businessId] ?? throw CalendarBusinessNotFound::withId($businessId);
    }
}
