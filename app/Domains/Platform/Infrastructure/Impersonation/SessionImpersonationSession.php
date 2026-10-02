<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Impersonation;

use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\Exceptions\BusinessOwnerDeactivated;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Models\User;
use App\Shared\Contracts\BusinessSelection;
use App\Shared\Contracts\ImpersonationStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

final class SessionImpersonationSession implements ImpersonationSession, ImpersonationStatus
{
    public const SESSION_KEY = 'impersonation';

    private const OWNER_PASSWORD_HASH_KEY = 'password_hash_'.PlatformGuard::OWNER_GUARD;

    private const IMPERSONATION_ID = 'impersonation_uuid';

    private const ADMIN_ID = 'admin_uuid';

    private const ACCOUNT_ID = 'account_uuid';

    private const BUSINESS_ID = 'business_uuid';

    private const BUSINESS_NAME = 'business_name';

    private const OWNER_NAME = 'owner_name';

    private const STARTED_AT = 'started_at';

    private const EXPIRES_AT = 'expires_at';

    private const WIRE_TIMEZONE = 'UTC';

    public function __construct(
        private readonly Request $request,
        private readonly PlatformGuard $guards,
        private readonly BusinessSelection $businessSelection,
    ) {}

    public function start(Impersonation $impersonation): void
    {
        $owner = User::query()->where('uuid', $impersonation->accountId)->first()
            ?? throw BusinessOwnerDeactivated::forBusiness($impersonation->businessId);

        $this->signOutCurrentOwnerDevice();

        $this->guards->owners()->login($owner);

        $this->businessSelection->rememberFor($impersonation->accountId, $impersonation->businessId);

        $session = $this->session();
        $session->forget(self::OWNER_PASSWORD_HASH_KEY);
        $session->put(self::SESSION_KEY, $this->serialize($impersonation));
        $session->regenerate();
    }

    public function end(): void
    {
        if (! $this->isPresent()) {
            return;
        }

        $impersonation = $this->current();

        $this->signOutCurrentOwnerDevice();

        if ($impersonation !== null) {
            $this->businessSelection->forgetFor($impersonation->accountId);
        }

        $session = $this->session();
        $session->forget([self::SESSION_KEY, self::OWNER_PASSWORD_HASH_KEY]);
        $session->regenerate();
    }

    public function isPresent(): bool
    {
        return $this->request->hasSession() && $this->session()->has(self::SESSION_KEY);
    }

    public function current(): ?Impersonation
    {
        if (! $this->isPresent()) {
            return null;
        }

        $payload = $this->session()->get(self::SESSION_KEY);

        return is_array($payload) ? $this->restore($payload) : null;
    }

    public function isActive(): bool
    {
        return $this->current() !== null;
    }

    public function describe(): ?array
    {
        $impersonation = $this->current();

        if ($impersonation === null) {
            return null;
        }

        return [
            'business_name' => $impersonation->businessName,
            'owner_name' => $impersonation->ownerName,
            'expires_at' => $this->formatInstant($impersonation->expiresAt),
        ];
    }

    private function signOutCurrentOwnerDevice(): void
    {
        $owners = $this->guards->owners();

        if (! $owners->check()) {
            return;
        }

        $owners->logoutCurrentDevice();
    }

    /**
     * @return array<string, string>
     */
    private function serialize(Impersonation $impersonation): array
    {
        return [
            self::IMPERSONATION_ID => $impersonation->id,
            self::ADMIN_ID => $impersonation->adminId,
            self::ACCOUNT_ID => $impersonation->accountId,
            self::BUSINESS_ID => $impersonation->businessId,
            self::BUSINESS_NAME => $impersonation->businessName,
            self::OWNER_NAME => $impersonation->ownerName,
            self::STARTED_AT => $this->formatInstant($impersonation->startedAt),
            self::EXPIRES_AT => $this->formatInstant($impersonation->expiresAt),
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function restore(array $payload): ?Impersonation
    {
        $texts = [self::IMPERSONATION_ID, self::ADMIN_ID, self::ACCOUNT_ID, self::BUSINESS_ID, self::BUSINESS_NAME, self::OWNER_NAME];

        foreach ($texts as $key) {
            if (! is_string($payload[$key] ?? null)) {
                return null;
            }
        }

        $startedAt = $this->parseInstant($payload[self::STARTED_AT] ?? null);
        $expiresAt = $this->parseInstant($payload[self::EXPIRES_AT] ?? null);

        if ($startedAt === null || $expiresAt === null) {
            return null;
        }

        return Impersonation::restore(
            id: $payload[self::IMPERSONATION_ID],
            adminId: $payload[self::ADMIN_ID],
            accountId: $payload[self::ACCOUNT_ID],
            businessId: $payload[self::BUSINESS_ID],
            businessName: $payload[self::BUSINESS_NAME],
            ownerName: $payload[self::OWNER_NAME],
            startedAt: $startedAt,
            expiresAt: $expiresAt,
        );
    }

    private function formatInstant(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone(self::WIRE_TIMEZONE))->format(DATE_ATOM);
    }

    private function parseInstant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        $instant = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        return $instant === false ? null : $instant;
    }

    private function session(): Session
    {
        return $this->request->session();
    }
}
