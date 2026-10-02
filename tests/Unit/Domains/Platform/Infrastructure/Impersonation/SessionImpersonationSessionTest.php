<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Impersonation\SessionImpersonationSession;
use Illuminate\Http\Request;
use Tests\Support\FakeBusinessSelection;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\PlatformSessionHarness;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function impersonationPayload(array $overrides = []): array
{
    return [
        'impersonation_uuid' => ImpersonationFixtures::IMPERSONATION_ID,
        'admin_uuid' => ImpersonationFixtures::ADMIN_ID,
        'account_uuid' => ImpersonationFixtures::ACCOUNT_ID,
        'business_uuid' => ImpersonationFixtures::BUSINESS_ID,
        'business_name' => ImpersonationFixtures::BUSINESS_NAME,
        'owner_name' => ImpersonationFixtures::OWNER_NAME,
        'started_at' => ImpersonationFixtures::NOW,
        'expires_at' => ImpersonationFixtures::EXPIRES_AT,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->harness = (new PlatformSessionHarness('/calendar'))->signInAdmin()->signInOwner();
});

describe('describing the impersonation to the page', function () {
    it('describes nothing when no impersonation is stored', function () {
        expect($this->harness->impersonations->describe())->toBeNull()
            ->and($this->harness->impersonations->isActive())->toBeFalse();
    });

    it('describes the business, the owner and the expiry as a UTC DATE_ATOM instant', function () {
        $this->harness->impersonating(ImpersonationFixtures::begun(new DateTimeImmutable('2026-09-25T17:00:00+02:00')));

        expect($this->harness->impersonations->describe())->toBe([
            'business_name' => ImpersonationFixtures::BUSINESS_NAME,
            'owner_name' => ImpersonationFixtures::OWNER_NAME,
            'expires_at' => ImpersonationFixtures::EXPIRES_AT,
        ])->and($this->harness->impersonations->isActive())->toBeTrue();
    });

    it('describes nothing for a stored impersonation it cannot read', function (mixed $payload) {
        $this->harness->holdingImpersonationPayload($payload);

        expect($this->harness->impersonations->current())->toBeNull()
            ->and($this->harness->impersonations->describe())->toBeNull()
            ->and($this->harness->impersonations->isActive())->toBeFalse()
            ->and($this->harness->impersonations->isPresent())->toBeTrue();
    })->with([
        'not an array' => ['impersonating'],
        'stored before impersonations carried an audit id' => [array_diff_key(impersonationPayload(), ['impersonation_uuid' => true])],
        'an int audit id' => [impersonationPayload(['impersonation_uuid' => 12])],
        'no admin' => [array_diff_key(impersonationPayload(), ['admin_uuid' => true])],
        'an int account id' => [impersonationPayload(['account_uuid' => 7])],
        'no expiry' => [array_diff_key(impersonationPayload(), ['expires_at' => true])],
        'an expiry not in DATE_ATOM' => [impersonationPayload(['expires_at' => '2026-09-25 16:00:00'])],
        'a start that is no instant' => [impersonationPayload(['started_at' => 'yesterday'])],
    ]);

    it('restores every field it stored, the audit id included', function () {
        $this->harness->impersonating(ImpersonationFixtures::begun());

        expect($this->harness->impersonations->current())->toEqual(ImpersonationFixtures::begun());
    });

    it('sees no impersonation on a request with no session', function () {
        $sessionless = new SessionImpersonationSession(new Request, $this->harness->guards, new FakeBusinessSelection);

        expect($sessionless->isPresent())->toBeFalse()
            ->and($sessionless->describe())->toBeNull();
    });
});

describe('ending the impersonation', function () {
    it('drops the impersonation, signs the owner out of this device and forgets their business selection', function () {
        $this->harness->impersonating(ImpersonationFixtures::begun());

        $this->harness->impersonations->end();

        expect($this->harness->holdsImpersonation())->toBeFalse()
            ->and($this->harness->owners->check())->toBeFalse()
            ->and($this->harness->businessSelection->forgotten)->toBe([ImpersonationFixtures::ACCOUNT_ID]);
    });

    it('leaves the platform admin signed in', function () {
        $this->harness->impersonating(ImpersonationFixtures::begun());

        $this->harness->impersonations->end();

        expect($this->harness->admins->check())->toBeTrue();
    });

    it('regenerates the session id so the impersonated session cannot be replayed', function () {
        $this->harness->impersonating(ImpersonationFixtures::begun());
        $before = $this->harness->session->getId();

        $this->harness->impersonations->end();

        expect($this->harness->session->getId())->not->toBe($before);
    });

    it('still drops an unreadable impersonation and signs the owner guard out', function () {
        $this->harness->holdingImpersonationPayload(['admin_uuid' => ImpersonationFixtures::ADMIN_ID]);

        $this->harness->impersonations->end();

        expect($this->harness->holdsImpersonation())->toBeFalse()
            ->and($this->harness->owners->check())->toBeFalse()
            ->and($this->harness->businessSelection->forgotten)->toBe([]);
    });

    it('does nothing when there is no impersonation to end', function () {
        $before = $this->harness->session->getId();

        $this->harness->impersonations->end();

        expect($this->harness->owners->check())->toBeTrue()
            ->and($this->harness->businessSelection->forgotten)->toBe([])
            ->and($this->harness->session->getId())->toBe($before);
    });
});
