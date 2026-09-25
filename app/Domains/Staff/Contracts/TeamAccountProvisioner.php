<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;
use App\Domains\Staff\ValueObjects\ProvisionedAccount;
use App\Domains\Staff\ValueObjects\StaffRole;

interface TeamAccountProvisioner
{
    /**
     * @throws InvalidProfileName
     * @throws InvalidTeamMemberEmail
     * @throws TeamMemberAlreadyExists
     */
    public function provision(StaffRole $level, string $name, string $email): ProvisionedAccount;

    /**
     * @throws StaffMemberNotFound
     */
    public function issueTemporaryPassword(string $accountId): ?string;
}
