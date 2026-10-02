<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\PlatformAdminRepository;
use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use Throwable;

final class FakePlatformAdminRepository implements PlatformAdminRepository
{
    /**
     * @var list<string>
     */
    private array $existingEmails;

    /**
     * @var list<PlatformAdmin>
     */
    public array $saved = [];

    /**
     * @var list<string>
     */
    public array $emailsAsked = [];

    private ?Throwable $saveRefusal = null;

    public function __construct(string ...$existingEmails)
    {
        $this->existingEmails = array_values($existingEmails);
    }

    public function existsByEmail(PlatformAdminEmail $email): bool
    {
        $this->emailsAsked[] = $email->value;

        return in_array($email->value, $this->existingEmails, true);
    }

    public function refusingToSaveWith(Throwable $refusal): self
    {
        $this->saveRefusal = $refusal;

        return $this;
    }

    public function save(PlatformAdmin $admin): void
    {
        if ($this->saveRefusal !== null) {
            throw $this->saveRefusal;
        }

        $this->saved[] = $admin;
    }
}
