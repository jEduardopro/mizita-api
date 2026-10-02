<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Application\UseCases\StopImpersonation;
use App\Domains\Platform\Infrastructure\Auth\PlatformActivity;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Auth\PlatformSignOut;
use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use App\Domains\Platform\Infrastructure\Http\Middleware\EnforceImpersonation;
use App\Domains\Platform\Infrastructure\Http\Middleware\RequirePlatformSession;
use App\Domains\Platform\Infrastructure\Impersonation\SensitiveRoutes;
use App\Domains\Platform\Infrastructure\Impersonation\SessionImpersonationSession;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Models\User;
use App\Shared\Contracts\Clock;
use DateTimeImmutable;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\Support\FakeBusinessSelection;

final class PlatformSessionHarness
{
    public const ADMIN_NAME = 'Grace Hopper';

    public const ADMIN_EMAIL = 'grace@mizita.test';

    public readonly Store $session;

    public readonly Request $request;

    public readonly SessionGuard $admins;

    public readonly SessionGuard $owners;

    public readonly PlatformGuard $guards;

    public readonly PlatformActivity $activity;

    public readonly FakeBusinessSelection $businessSelection;

    public readonly SessionImpersonationSession $impersonations;

    public readonly RecordingImpersonationAuditTrail $auditTrail;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(string $uri, string $method = 'GET', array $headers = [])
    {
        $this->session = new Store('mizita_session', new ArraySessionHandler(120));
        $this->request = Request::create($uri, $method);

        foreach ($headers as $name => $value) {
            $this->request->headers->set($name, $value);
        }

        $this->request->setLaravelSession($this->session);

        $this->admins = $this->sessionGuard(PlatformGuard::NAME);
        $this->owners = $this->sessionGuard(PlatformGuard::OWNER_GUARD);
        $this->guards = new PlatformGuard(new FakeAuthFactory([
            PlatformGuard::NAME => $this->admins,
            PlatformGuard::OWNER_GUARD => $this->owners,
        ]));
        $this->activity = new PlatformActivity($this->session);
        $this->businessSelection = new FakeBusinessSelection;
        $this->impersonations = new SessionImpersonationSession($this->request, $this->guards, $this->businessSelection);
        $this->auditTrail = new RecordingImpersonationAuditTrail;
    }

    public function signInAdmin(string $adminId = ImpersonationFixtures::ADMIN_ID): self
    {
        $this->admins->login((new PlatformAdminModel)->setRawAttributes([
            'id' => 1,
            'uuid' => $adminId,
            'name' => self::ADMIN_NAME,
            'email' => self::ADMIN_EMAIL,
        ]));

        return $this;
    }

    public function signInOwner(string $accountId = ImpersonationFixtures::ACCOUNT_ID): self
    {
        $this->owners->login((new User)->setRawAttributes(['id' => 7, 'uuid' => $accountId]));

        return $this;
    }

    public function lastActiveAt(DateTimeImmutable $instant): self
    {
        $this->activity->touch($instant);

        return $this;
    }

    public function impersonating(Impersonation $impersonation): self
    {
        return $this->holdingImpersonationPayload([
            'impersonation_uuid' => $impersonation->id,
            'admin_uuid' => $impersonation->adminId,
            'account_uuid' => $impersonation->accountId,
            'business_uuid' => $impersonation->businessId,
            'business_name' => $impersonation->businessName,
            'owner_name' => $impersonation->ownerName,
            'started_at' => $impersonation->startedAt->format(DATE_ATOM),
            'expires_at' => $impersonation->expiresAt->format(DATE_ATOM),
        ]);
    }

    public function holdingImpersonationPayload(mixed $payload): self
    {
        $this->session->put(SessionImpersonationSession::SESSION_KEY, $payload);

        return $this;
    }

    public function onRoute(string $name): self
    {
        $route = (new Route([$this->request->getMethod()], $this->request->path(), static fn () => null))->name($name);

        $this->request->setRouteResolver(static fn (): Route => $route);

        return $this;
    }

    public function holdsImpersonation(): bool
    {
        return $this->session->has(SessionImpersonationSession::SESSION_KEY);
    }

    public function enforceImpersonation(Clock $clock): EnforceImpersonation
    {
        $stopImpersonation = new StopImpersonation($this->impersonations, $this->auditTrail, $clock);

        return new EnforceImpersonation(
            $this->impersonations,
            $stopImpersonation,
            new PlatformSignOut($stopImpersonation, $this->guards, $this->activity),
            $this->activity,
            $this->guards,
            new SensitiveRoutes,
            $clock,
        );
    }

    public function requirePlatformSession(Clock $clock): RequirePlatformSession
    {
        $stopImpersonation = new StopImpersonation($this->impersonations, $this->auditTrail, $clock);

        return new RequirePlatformSession(
            $this->guards,
            $this->activity,
            new PlatformSignOut($stopImpersonation, $this->guards, $this->activity),
            $clock,
        );
    }

    private function sessionGuard(string $name): SessionGuard
    {
        $guard = new SessionGuard($name, new NoStoredUserProvider, $this->session, $this->request);
        $guard->setCookieJar(new CookieJar);

        return $guard;
    }
}
