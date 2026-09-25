<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarAuthorizer;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use Throwable;

final class FakeCalendarAuthorizer implements CalendarAuthorizer
{
    public const AUTHORIZATION_ENDPOINT = 'https://accounts.google.test/o/oauth2/v2/auth?state=';

    /**
     * @var list<string>
     */
    public array $exchangedCodes = [];

    private CalendarGrant $grant;

    private ?Throwable $exchangeFailure = null;

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
    ) {
        $this->grant = IntegrationsFixtures::grant();
    }

    public function grantWith(CalendarGrant $grant): self
    {
        $this->grant = $grant;

        return $this;
    }

    public function failExchangeWith(Throwable $failure): self
    {
        $this->exchangeFailure = $failure;

        return $this;
    }

    public function authorizationUrl(string $state): string
    {
        $this->journal->record('authorizer.authorizationUrl');

        return self::AUTHORIZATION_ENDPOINT.$state;
    }

    public function exchange(string $code): CalendarGrant
    {
        $this->journal->record('authorizer.exchange');
        $this->exchangedCodes[] = $code;

        if ($this->exchangeFailure !== null) {
            throw $this->exchangeFailure;
        }

        return $this->grant;
    }
}
