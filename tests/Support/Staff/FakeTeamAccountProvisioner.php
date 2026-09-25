<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\ProvisionedAccount;
use App\Domains\Staff\ValueObjects\StaffRole;
use RuntimeException;
use Throwable;

final class FakeTeamAccountProvisioner implements TeamAccountProvisioner
{
    /**
     * @var array<string, ProvisionedAccount>
     */
    private array $accounts = [];

    /**
     * @var array<string, ?string>
     */
    private array $temporaryPasswords = [];

    /**
     * @var array<string, Throwable>
     */
    private array $provisionRefusals = [];

    private ?Throwable $issueRefusal = null;

    /**
     * @var list<array{level: StaffRole, name: string, email: string}>
     */
    public array $provisions = [];

    /**
     * @var list<string>
     */
    public array $issued = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public function provides(string $email, string $accountId, ?string $temporaryPassword): self
    {
        $this->accounts[$email] = new ProvisionedAccount($accountId, $temporaryPassword);

        return $this;
    }

    public function issues(string $accountId, ?string $temporaryPassword): self
    {
        $this->temporaryPasswords[$accountId] = $temporaryPassword;

        return $this;
    }

    public function refuseProvisioningWith(string $email, Throwable $refusal): self
    {
        $this->provisionRefusals[$email] = $refusal;

        return $this;
    }

    public function refuseIssuingWith(Throwable $refusal): self
    {
        $this->issueRefusal = $refusal;

        return $this;
    }

    public function provision(StaffRole $level, string $name, string $email): ProvisionedAccount
    {
        $this->journal->record('accounts.provision');
        $this->provisions[] = ['level' => $level, 'name' => $name, 'email' => $email];

        if (isset($this->provisionRefusals[$email])) {
            throw $this->provisionRefusals[$email];
        }

        return $this->accounts[$email]
            ?? throw new RuntimeException("FakeTeamAccountProvisioner was not told which account [{$email}] maps to.");
    }

    public function issueTemporaryPassword(string $accountId): ?string
    {
        $this->journal->record('accounts.issue_password');
        $this->issued[] = $accountId;

        if ($this->issueRefusal !== null) {
            throw $this->issueRefusal;
        }

        if (! array_key_exists($accountId, $this->temporaryPasswords)) {
            throw StaffMemberNotFound::forAccount($accountId);
        }

        return $this->temporaryPasswords[$accountId];
    }
}
