<?php

declare(strict_types=1);

use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Infrastructure\MembershipCurrentBusinessResolver;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessMembership;
use Tests\Support\FakeBusinessSelection;
use Tests\Support\FakePausedBusinessAccess;

const RESOLVER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000d00a1';

const RESOLVER_OTHER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000d00a2';

const RESOLVER_OWNED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b1';

const RESOLVER_JOINED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b2';

const RESOLVER_PAUSED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b3';

const RESOLVER_FORMER_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b4';

const RESOLVER_FOREIGN_BUSINESS_UUID = '01930000-0000-7000-8000-0000000d00b9';

/**
 * @param  list<string>  $accessible
 */
function currentBusinessResolver(
    array $accessible,
    FakePausedBusinessAccess $pausedAccess = new FakePausedBusinessAccess,
    FakeBusinessSelection $selection = new FakeBusinessSelection,
): MembershipCurrentBusinessResolver {
    return new MembershipCurrentBusinessResolver(
        new FakeBusinessMembership([RESOLVER_ACCOUNT_UUID => $accessible]),
        $pausedAccess,
        $selection,
    );
}

function resolverPausedAccess(string ...$paused): FakePausedBusinessAccess
{
    return new FakePausedBusinessAccess([RESOLVER_ACCOUNT_UUID => $paused]);
}

function resolverSelection(string $businessId): FakeBusinessSelection
{
    return new FakeBusinessSelection([RESOLVER_ACCOUNT_UUID => $businessId]);
}

function currentBusinessRefusal(Closure $resolve): DomainFailure
{
    try {
        $resolve();
    } catch (DomainFailure $failure) {
        return $failure;
    }

    throw new RuntimeException('The resolver let the caller through.');
}

describe('a header naming a business', function () {
    it('resolves the business the header names when the account belongs to it', function () {
        $resolved = currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID])
            ->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_JOINED_BUSINESS_UUID);

        expect($resolved)->toBe(RESOLVER_JOINED_BUSINESS_UUID);
    });

    it('wins over the business the account selected earlier', function () {
        $resolved = currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID],
            selection: resolverSelection(RESOLVER_JOINED_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_OWNED_BUSINESS_UUID);

        expect($resolved)->toBe(RESOLVER_OWNED_BUSINESS_UUID);
    });

    it('leaves the stored selection in place', function () {
        $selection = resolverSelection(RESOLVER_JOINED_BUSINESS_UUID);

        currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID], selection: $selection)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_OWNED_BUSINESS_UUID);

        expect($selection->forgotten)->toBe([])
            ->and($selection->remembered)->toBe([])
            ->and($selection->selectedBusinessIdFor(RESOLVER_ACCOUNT_UUID))->toBe(RESOLVER_JOINED_BUSINESS_UUID);
    });

    it('never asks about paused access when the account belongs to the business', function () {
        $pausedAccess = resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID);

        currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID], $pausedAccess)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_OWNED_BUSINESS_UUID);

        expect($pausedAccess->lookups)->toBe([]);
    });

    it('refuses a paused business with team_access_paused although another business is accessible', function () {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID],
            resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_PAUSED_BUSINESS_UUID));

        expect($refusal)->toBeInstanceOf(TeamAccessPaused::class)
            ->and($refusal->errorCode())->toBe('team_access_paused')
            ->and($refusal->kind())->toBe(DomainFailureKind::Forbidden);
    });

    it('refuses a business the account has no membership in with business_not_accessible', function (array $paused) {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID],
            resolverPausedAccess(...$paused),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_FOREIGN_BUSINESS_UUID));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('business_not_accessible')
            ->and($refusal->kind())->toBe(DomainFailureKind::Forbidden);
    })->with([
        'with no paused membership' => [[]],
        'while another membership is paused' => [[RESOLVER_PAUSED_BUSINESS_UUID]],
    ]);

    it('refuses a business the account has no membership in even when it is the stored selection', function () {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID],
            selection: resolverSelection(RESOLVER_FORMER_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, RESOLVER_FORMER_BUSINESS_UUID));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('business_not_accessible');
    });
});

describe('no header and a stored selection', function () {
    it('resolves the selected business while the account still belongs to it', function () {
        $resolved = currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID],
            selection: resolverSelection(RESOLVER_JOINED_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($resolved)->toBe(RESOLVER_JOINED_BUSINESS_UUID);
    });

    it('keeps a selection that is still accessible', function () {
        $selection = resolverSelection(RESOLVER_JOINED_BUSINESS_UUID);

        currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID], selection: $selection)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($selection->forgotten)->toBe([])
            ->and($selection->selectedBusinessIdFor(RESOLVER_ACCOUNT_UUID))->toBe(RESOLVER_JOINED_BUSINESS_UUID);
    });

    it('falls back to the first accessible business when the selection is no longer accessible', function (string $stale) {
        $resolved = currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID],
            resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID),
            resolverSelection($stale),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($resolved)->toBe(RESOLVER_OWNED_BUSINESS_UUID);
    })->with([
        'a membership that ended' => [RESOLVER_FORMER_BUSINESS_UUID],
        'a business whose team access is paused' => [RESOLVER_PAUSED_BUSINESS_UUID],
    ]);

    it('forgets a selection that is no longer accessible, once, for the caller', function (string $stale) {
        $selection = resolverSelection($stale);

        currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID],
            resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID),
            $selection,
        )->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($selection->forgotten)->toBe([RESOLVER_ACCOUNT_UUID])
            ->and($selection->selectedBusinessIdFor(RESOLVER_ACCOUNT_UUID))->toBeNull();
    })->with([
        'a membership that ended' => [RESOLVER_FORMER_BUSINESS_UUID],
        'a business whose team access is paused' => [RESOLVER_PAUSED_BUSINESS_UUID],
    ]);

    it('ignores a stale selection without refusing and without asking about paused access', function () {
        $pausedAccess = resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID);

        $resolved = currentBusinessResolver(
            [RESOLVER_OWNED_BUSINESS_UUID],
            $pausedAccess,
            resolverSelection(RESOLVER_PAUSED_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($resolved)->toBe(RESOLVER_OWNED_BUSINESS_UUID)
            ->and($pausedAccess->lookups)->toBe([]);
    });

    it('ignores a selection stored for another account', function () {
        $selection = new FakeBusinessSelection([RESOLVER_OTHER_ACCOUNT_UUID => RESOLVER_JOINED_BUSINESS_UUID]);

        $resolved = currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID, RESOLVER_JOINED_BUSINESS_UUID], selection: $selection)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($resolved)->toBe(RESOLVER_OWNED_BUSINESS_UUID)
            ->and($selection->forgotten)->toBe([]);
    });
});

describe('no header and no stored selection', function () {
    it('resolves the first accessible business', function () {
        $resolved = currentBusinessResolver([RESOLVER_JOINED_BUSINESS_UUID, RESOLVER_OWNED_BUSINESS_UUID])
            ->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($resolved)->toBe(RESOLVER_JOINED_BUSINESS_UUID);
    });

    it('does not store the business it fell back to', function () {
        $selection = new FakeBusinessSelection;

        currentBusinessResolver([RESOLVER_OWNED_BUSINESS_UUID], selection: $selection)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, null);

        expect($selection->remembered)->toBe([])
            ->and($selection->forgotten)->toBe([]);
    });
});

describe('an account with no accessible business', function () {
    it('refuses with team_access_paused when one of its memberships is paused', function (?string $requestedBusiness) {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver(
            [],
            resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, $requestedBusiness));

        expect($refusal)->toBeInstanceOf(TeamAccessPaused::class)
            ->and($refusal->errorCode())->toBe('team_access_paused');
    })->with([
        'no header' => [null],
        'a header naming the paused business' => [RESOLVER_PAUSED_BUSINESS_UUID],
        'a header naming a foreign business' => [RESOLVER_FOREIGN_BUSINESS_UUID],
    ]);

    it('asks about the paused access of the account being resolved', function () {
        $pausedAccess = resolverPausedAccess(RESOLVER_PAUSED_BUSINESS_UUID);

        currentBusinessRefusal(fn () => currentBusinessResolver([], $pausedAccess)
            ->resolveFor(RESOLVER_ACCOUNT_UUID, null));

        expect($pausedAccess->lookups)->toBe([RESOLVER_ACCOUNT_UUID]);
    });

    it('refuses with no_business when none of its memberships is paused', function (?string $requestedBusiness) {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver([])
            ->resolveFor(RESOLVER_ACCOUNT_UUID, $requestedBusiness));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('no_business')
            ->and($refusal->kind())->toBe(DomainFailureKind::Forbidden);
    })->with([
        'no header' => [null],
        'a header naming a foreign business' => [RESOLVER_FOREIGN_BUSINESS_UUID],
    ]);

    it('refuses with no_business even when a selection is stored', function () {
        $refusal = currentBusinessRefusal(fn () => currentBusinessResolver(
            [],
            selection: resolverSelection(RESOLVER_FORMER_BUSINESS_UUID),
        )->resolveFor(RESOLVER_ACCOUNT_UUID, null));

        expect($refusal)->toBeInstanceOf(BusinessAccessDenied::class)
            ->and($refusal->errorCode())->toBe('no_business');
    });
});
