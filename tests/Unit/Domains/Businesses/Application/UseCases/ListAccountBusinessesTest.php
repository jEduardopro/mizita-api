<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\AccountBusinessData;
use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\ListAccountBusinessesInput;
use App\Domains\Businesses\Application\UseCases\ListAccountBusinesses;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\ValueObjects\MembershipRole;
use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\FakeCurrentBusinessResolver;
use Tests\Support\Businesses\FakeMembershipRoles;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessMembership;

function aListedBusiness(string $id, string $name, string $slug): Business
{
    return OnboardingFixtures::business(id: $id, name: $name, slug: $slug);
}

/**
 * @param  list<AccountBusinessData>  $listed
 * @return list<string>
 */
function listedBusinessIds(array $listed): array
{
    return array_map(static fn (AccountBusinessData $item): string => $item->business->id, $listed);
}

/**
 * @param  list<AccountBusinessData>  $listed
 * @return array<string, bool>
 */
function currentFlagsByBusiness(array $listed): array
{
    return array_combine(
        listedBusinessIds($listed),
        array_map(static fn (AccountBusinessData $item): bool => $item->isCurrent, $listed),
    );
}

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';
    $this->anotherAccountId = '01930000-0000-7000-8000-0000000000a2';
    $this->ownedBusinessId = '01930000-0000-7000-8000-000000000001';
    $this->staffedBusinessId = '01930000-0000-7000-8000-000000000002';
    $this->thirdBusinessId = '01930000-0000-7000-8000-000000000003';

    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->logo = new FakeBusinessLogo;
    $this->resolver = new FakeCurrentBusinessResolver($this->ownedBusinessId);
    $this->roles = new FakeMembershipRoles;

    $this->useCaseFor = function (array $membershipsByAccount): ListAccountBusinesses {
        return new ListAccountBusinesses(
            new FakeBusinessMembership($membershipsByAccount),
            $this->businesses,
            $this->logo,
            $this->resolver,
            $this->roles,
        );
    };

    $this->listOwnedAndStaffed = function (?string $requestedBusinessId = null): array {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->with([$this->ownedBusinessId, $this->staffedBusinessId])
            ->andReturn([
                aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu'),
                aListedBusiness($this->staffedBusinessId, 'Salón Aurora', 'salon-aurora'),
            ]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId],
        ]);

        return $useCase->handle(new ListAccountBusinessesInput($this->accountId, $requestedBusinessId))->value();
    };
});

it('returns the account businesses as data, field by field', function () {
    $this->businesses->shouldReceive('findManyByIds')->once()
        ->with([$this->ownedBusinessId])
        ->andReturn([aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu')]);

    $this->logo->store($this->ownedBusinessId, SettingsFixtures::LOGO_URL);
    $this->roles = new FakeMembershipRoles([$this->accountId => [$this->ownedBusinessId => MembershipRole::Owner]]);

    $useCase = ($this->useCaseFor)([$this->accountId => [$this->ownedBusinessId]]);

    $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

    expect($listed)->toHaveCount(1)
        ->and($listed[0])->toBeInstanceOf(AccountBusinessData::class)
        ->and($listed[0]->role)->toBe(MembershipRole::Owner)
        ->and($listed[0]->isCurrent)->toBeTrue()
        ->and($listed[0]->business)->toBeInstanceOf(BusinessData::class)
        ->and($listed[0]->business->id)->toBe($this->ownedBusinessId)
        ->and($listed[0]->business->name)->toBe('Barbería Ñandú')
        ->and($listed[0]->business->slug)->toBe('barberia-nandu')
        ->and($listed[0]->business->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($listed[0]->business->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($listed[0]->business->contactEmail)->toBeNull()
        ->and($listed[0]->business->about)->toBeNull()
        ->and($listed[0]->business->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
        ->and($listed[0]->business->createdAt)->toEqual(OnboardingFixtures::now());
});

describe('the role the account holds in each business', function () {
    it('marks the owned business as owner and the staffed one as staff', function () {
        $this->roles = new FakeMembershipRoles([$this->accountId => [
            $this->ownedBusinessId => MembershipRole::Owner,
            $this->staffedBusinessId => MembershipRole::Staff,
        ]]);

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[0]->business->id)->toBe($this->ownedBusinessId)
            ->and($listed[0]->role)->toBe(MembershipRole::Owner)
            ->and($listed[1]->business->id)->toBe($this->staffedBusinessId)
            ->and($listed[1]->role)->toBe(MembershipRole::Staff);
    });

    it('falls back to staff for a business the roles port names no role for', function () {
        $this->roles = new FakeMembershipRoles([$this->accountId => [
            $this->ownedBusinessId => MembershipRole::Owner,
        ]]);

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[1]->role)->toBe(MembershipRole::Staff);
    });

    it('never falls back to owner when the roles port knows nothing at all', function () {
        $listed = ($this->listOwnedAndStaffed)();

        expect(array_map(static fn (AccountBusinessData $item): MembershipRole => $item->role, $listed))
            ->toBe([MembershipRole::Staff, MembershipRole::Staff]);
    });

    it('asks for the roles of the account it was given, once', function () {
        ($this->listOwnedAndStaffed)();

        expect($this->roles->lookups)->toBe([$this->accountId]);
    });

    it('never lends the account a role another account holds', function () {
        $this->roles = new FakeMembershipRoles([$this->anotherAccountId => [
            $this->staffedBusinessId => MembershipRole::Owner,
        ]]);

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[1]->role)->toBe(MembershipRole::Staff);
    });
});

describe('which business is current', function () {
    it('flags the business the resolver settled on, and only that one', function () {
        $listed = ($this->listOwnedAndStaffed)();

        expect(currentFlagsByBusiness($listed))->toBe([
            $this->ownedBusinessId => true,
            $this->staffedBusinessId => false,
        ]);
    });

    it('asks the resolver once for the account, with no requested business when none was sent', function () {
        ($this->listOwnedAndStaffed)();

        expect($this->resolver->resolutions)->toBe([
            ['accountId' => $this->accountId, 'requestedBusinessId' => null],
        ]);
    });

    it('hands the requested business to the resolver', function () {
        $this->resolver = new FakeCurrentBusinessResolver;

        ($this->listOwnedAndStaffed)($this->staffedBusinessId);

        expect($this->resolver->resolutions)->toBe([
            ['accountId' => $this->accountId, 'requestedBusinessId' => $this->staffedBusinessId],
        ]);
    });

    it('flags the requested business as current once the resolver accepts it', function () {
        $this->resolver = new FakeCurrentBusinessResolver;

        $listed = ($this->listOwnedAndStaffed)($this->staffedBusinessId);

        expect(currentFlagsByBusiness($listed))->toBe([
            $this->ownedBusinessId => false,
            $this->staffedBusinessId => true,
        ]);
    });

    it('follows the verdict of the resolver rather than the raw request', function () {
        $this->resolver = new FakeCurrentBusinessResolver($this->ownedBusinessId);

        $listed = ($this->listOwnedAndStaffed)($this->staffedBusinessId);

        expect(currentFlagsByBusiness($listed))->toBe([
            $this->ownedBusinessId => true,
            $this->staffedBusinessId => false,
        ]);
    });

    it('keeps the current flag on the business it belongs to, whatever its role', function () {
        $this->resolver = new FakeCurrentBusinessResolver($this->staffedBusinessId);
        $this->roles = new FakeMembershipRoles([$this->accountId => [
            $this->ownedBusinessId => MembershipRole::Owner,
            $this->staffedBusinessId => MembershipRole::Staff,
        ]]);

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[0]->role)->toBe(MembershipRole::Owner)
            ->and($listed[0]->isCurrent)->toBeFalse()
            ->and($listed[1]->role)->toBe(MembershipRole::Staff)
            ->and($listed[1]->isCurrent)->toBeTrue();
    });
});

describe('when the current business cannot be resolved', function () {
    beforeEach(function () {
        $this->listRefusedWith = function (Throwable $refusal): UseCaseResponse {
            $this->resolver = (new FakeCurrentBusinessResolver)->refusingWith($refusal);

            $useCase = ($this->useCaseFor)([
                $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId],
            ]);

            return $useCase->handle(new ListAccountBusinessesInput($this->accountId, $this->thirdBusinessId));
        };
    });

    it('returns the refusal of the resolver as a failure', function (Throwable $refusal, string $code) {
        $this->businesses->shouldNotReceive('findManyByIds');

        $response = ($this->listRefusedWith)($refusal);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($response->error()->cause())->toBe($refusal);
    })->with([
        'a business the account cannot access' => [
            fn () => BusinessAccessDenied::businessNotAccessible('01930000-0000-7000-8000-000000000003'),
            'business_not_accessible',
        ],
        'a business whose team access is paused' => [
            fn () => TeamAccessPaused::forBusiness('01930000-0000-7000-8000-000000000003'),
            'team_access_paused',
        ],
        'an account the resolver finds no business for' => [
            fn () => BusinessAccessDenied::accountHasNoBusiness(),
            'no_business',
        ],
    ]);

    it('reads no business, no role and no logo once refused', function () {
        $this->businesses->shouldNotReceive('findManyByIds');

        ($this->listRefusedWith)(BusinessAccessDenied::businessNotAccessible($this->thirdBusinessId));

        expect($this->roles->lookups)->toBe([])
            ->and($this->logo->reads)->toBe([]);
    });

    it('lets a resolver error that is not a refusal escape', function () {
        expect(fn () => ($this->listRefusedWith)(new RuntimeException('session store unavailable')))
            ->toThrow(RuntimeException::class, 'session store unavailable');
    });
});

describe('the logo each business carries', function () {
    it('gives each business the url the port holds for it, and null to the one with no logo', function () {
        $this->logo->store($this->staffedBusinessId, SettingsFixtures::LOGO_URL);

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[0]->business->logoUrl)->toBeNull()
            ->and($listed[1]->business->logoUrl)->toBe(SettingsFixtures::LOGO_URL);
    });

    it('never hands one business the logo of another', function () {
        $this->logo->store($this->ownedBusinessId, 'https://mizita.test/media/1/nandu.png')
            ->store($this->staffedBusinessId, 'https://mizita.test/media/2/aurora.png');

        $listed = ($this->listOwnedAndStaffed)();

        expect($listed[0]->business->logoUrl)->toBe('https://mizita.test/media/1/nandu.png')
            ->and($listed[1]->business->logoUrl)->toBe('https://mizita.test/media/2/aurora.png');
    });

    it('asks the port once for each business id it lists', function () {
        ($this->listOwnedAndStaffed)();

        expect($this->logo->reads)->toBe([$this->ownedBusinessId, $this->staffedBusinessId]);
    });

    it('never writes a logo while listing', function () {
        ($this->listOwnedAndStaffed)();

        expect($this->logo->replacements)->toBe([])
            ->and($this->logo->removals)->toBe([]);
    });
});

describe('the order the account sees', function () {
    it('asks for the businesses in the order the membership port named them', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->with([$this->ownedBusinessId, $this->staffedBusinessId, $this->thirdBusinessId])
            ->andReturn([]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId, $this->thirdBusinessId],
        ]);

        $useCase->handle(new ListAccountBusinessesInput($this->accountId));
    });

    it('hands back the businesses in the order the repository returned them', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->andReturn([
            aListedBusiness($this->thirdBusinessId, 'Zeta Studio', 'zeta-studio'),
            aListedBusiness($this->ownedBusinessId, 'Alfa Barbers', 'alfa-barbers'),
            aListedBusiness($this->staffedBusinessId, 'Beta Salon', 'beta-salon'),
        ]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->thirdBusinessId, $this->ownedBusinessId, $this->staffedBusinessId],
        ]);

        $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

        expect(listedBusinessIds($listed))
            ->toBe([$this->thirdBusinessId, $this->ownedBusinessId, $this->staffedBusinessId]);
    });

    it('never moves the current business to the front of the list', function () {
        $this->resolver = new FakeCurrentBusinessResolver($this->staffedBusinessId);

        $listed = ($this->listOwnedAndStaffed)();

        expect(listedBusinessIds($listed))->toBe([$this->ownedBusinessId, $this->staffedBusinessId]);
    });

    it('hands back a list, never an entity and never a map keyed by uuid', function () {
        $listed = ($this->listOwnedAndStaffed)();

        expect(array_keys($listed))->toBe([0, 1])
            ->and($listed)->each->toBeInstanceOf(AccountBusinessData::class);
    });
});

describe('an account that operates no business', function () {
    it('returns an empty list as a success, because operating no business is not a refusal', function () {
        $useCase = ($this->useCaseFor)([]);

        $response = $useCase->handle(new ListAccountBusinessesInput($this->accountId));

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });

    it('never asks the resolver, so an account with no business is not refused', function () {
        $this->resolver = (new FakeCurrentBusinessResolver)
            ->refusingWith(BusinessAccessDenied::accountHasNoBusiness());

        $useCase = ($this->useCaseFor)([]);

        $response = $useCase->handle(new ListAccountBusinessesInput($this->accountId, $this->ownedBusinessId));

        expect($response->succeeded())->toBeTrue()
            ->and($this->resolver->resolutions)->toBe([]);
    });

    it('touches neither the repository, the roles nor the logo', function () {
        $this->businesses->shouldNotReceive('findManyByIds');
        $this->businesses->shouldNotReceive('findById');

        $useCase = ($this->useCaseFor)([$this->anotherAccountId => [$this->ownedBusinessId]]);

        $useCase->handle(new ListAccountBusinessesInput($this->accountId));

        expect($this->roles->lookups)->toBe([])
            ->and($this->logo->reads)->toBe([]);
    });
});

describe('the businesses of another account', function () {
    it('asks only for the memberships of the account it was given', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->with([$this->staffedBusinessId])
            ->andReturn([aListedBusiness($this->staffedBusinessId, 'Salón Aurora', 'salon-aurora')]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->staffedBusinessId],
            $this->anotherAccountId => [$this->ownedBusinessId, $this->thirdBusinessId],
        ]);

        $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

        expect(listedBusinessIds($listed))->toBe([$this->staffedBusinessId]);
    });

    it('reads the account id off the input and nowhere else', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->with([$this->ownedBusinessId, $this->thirdBusinessId])
            ->andReturn([]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->staffedBusinessId],
            $this->anotherAccountId => [$this->ownedBusinessId, $this->thirdBusinessId],
        ]);

        $useCase->handle(new ListAccountBusinessesInput($this->anotherAccountId));

        expect($this->resolver->resolutions[0]['accountId'])->toBe($this->anotherAccountId)
            ->and($this->roles->lookups)->toBe([$this->anotherAccountId]);
    });

    it('returns nothing for an account the membership port does not know', function () {
        $this->businesses->shouldNotReceive('findManyByIds');

        $useCase = ($this->useCaseFor)([$this->accountId => [$this->ownedBusinessId]]);

        expect($useCase->handle(new ListAccountBusinessesInput('unknown-account'))->value())->toBe([]);
    });
});

describe('the response it hands back', function () {
    it('reports success and carries no warning', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->andReturn([aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu')]);

        $useCase = ($this->useCaseFor)([$this->accountId => [$this->ownedBusinessId]]);

        $response = $useCase->handle(new ListAccountBusinessesInput($this->accountId));

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([]);
    });

    it('lets a storage failure escape rather than dressing it as a refusal', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()
            ->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));

        $useCase = ($this->useCaseFor)([$this->accountId => [$this->ownedBusinessId]]);

        expect(fn () => $useCase->handle(new ListAccountBusinessesInput($this->accountId)))
            ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure');
    });
});
