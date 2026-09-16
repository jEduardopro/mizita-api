<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\ListAccountBusinessesInput;
use App\Domains\Businesses\Application\UseCases\ListAccountBusinesses;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessMembership;

function aListedBusiness(string $id, string $name, string $slug): Business
{
    return OnboardingFixtures::business(id: $id, name: $name, slug: $slug);
}

/**
 * @param  list<BusinessData>  $businesses
 * @return list<string>
 */
function listedBusinessIds(array $businesses): array
{
    return array_map(static fn (BusinessData $business): string => $business->id, $businesses);
}

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';
    $this->anotherAccountId = '01930000-0000-7000-8000-0000000000a2';
    $this->ownedBusinessId = '01930000-0000-7000-8000-000000000001';
    $this->staffedBusinessId = '01930000-0000-7000-8000-000000000002';
    $this->thirdBusinessId = '01930000-0000-7000-8000-000000000003';

    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->logo = new FakeBusinessLogo;

    $this->useCaseFor = function (array $membershipsByAccount): ListAccountBusinesses {
        return new ListAccountBusinesses(
            new FakeBusinessMembership($membershipsByAccount),
            $this->businesses,
            $this->logo,
        );
    };
});

it('returns the account businesses as data, field by field', function () {
    $this->businesses->shouldReceive('findManyByIds')->once()
        ->with([$this->ownedBusinessId])
        ->andReturn([aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu')]);

    $this->logo->store($this->ownedBusinessId, SettingsFixtures::LOGO_URL);

    $useCase = ($this->useCaseFor)([$this->accountId => [$this->ownedBusinessId]]);

    $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

    expect($listed)->toHaveCount(1)
        ->and($listed[0])->toBeInstanceOf(BusinessData::class)
        ->and($listed[0]->id)->toBe($this->ownedBusinessId)
        ->and($listed[0]->name)->toBe('Barbería Ñandú')
        ->and($listed[0]->slug)->toBe('barberia-nandu')
        ->and($listed[0]->timezone)->toBe(OnboardingFixtures::TIMEZONE)
        ->and($listed[0]->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
        ->and($listed[0]->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
        ->and($listed[0]->createdAt)->toEqual(OnboardingFixtures::now());
});

describe('the logo each business carries', function () {
    beforeEach(function () {
        $this->listTwoBusinesses = function (): array {
            $this->businesses->shouldReceive('findManyByIds')->once()->andReturn([
                aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu'),
                aListedBusiness($this->staffedBusinessId, 'Salón Aurora', 'salon-aurora'),
            ]);

            $useCase = ($this->useCaseFor)([
                $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId],
            ]);

            return $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();
        };
    });

    it('gives each business the url the port holds for it, and null to the one with no logo', function () {
        $this->logo->store($this->staffedBusinessId, SettingsFixtures::LOGO_URL);

        $listed = ($this->listTwoBusinesses)();

        expect($listed[0]->logoUrl)->toBeNull()
            ->and($listed[1]->logoUrl)->toBe(SettingsFixtures::LOGO_URL);
    });

    it('never hands one business the logo of another', function () {
        $this->logo->store($this->ownedBusinessId, 'https://mizita.test/media/1/nandu.png')
            ->store($this->staffedBusinessId, 'https://mizita.test/media/2/aurora.png');

        $listed = ($this->listTwoBusinesses)();

        expect($listed[0]->logoUrl)->toBe('https://mizita.test/media/1/nandu.png')
            ->and($listed[1]->logoUrl)->toBe('https://mizita.test/media/2/aurora.png');
    });

    it('asks the port once for each business id it lists', function () {
        ($this->listTwoBusinesses)();

        expect($this->logo->reads)->toBe([$this->ownedBusinessId, $this->staffedBusinessId]);
    });

    it('asks the port for no logo at all when the account operates no business', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->with([])->andReturn([]);

        $useCase = ($this->useCaseFor)([]);

        $useCase->handle(new ListAccountBusinessesInput($this->accountId));

        expect($this->logo->reads)->toBe([]);
    });

    it('never writes a logo while listing', function () {
        ($this->listTwoBusinesses)();

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

    it('hands back the businesses in that same order, so index zero is the current business', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->andReturn([
            aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu'),
            aListedBusiness($this->staffedBusinessId, 'Salón Aurora', 'salon-aurora'),
            aListedBusiness($this->thirdBusinessId, 'Studio 54', 'studio-54'),
        ]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId, $this->thirdBusinessId],
        ]);

        $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

        expect(listedBusinessIds($listed))
            ->toBe([$this->ownedBusinessId, $this->staffedBusinessId, $this->thirdBusinessId])
            ->and($listed[0]->name)->toBe('Barbería Ñandú');
    });

    it('never sorts the repository rows by a field of its own', function () {
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

    it('hands back a list, never an entity and never a map keyed by uuid', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->andReturn([
            aListedBusiness($this->ownedBusinessId, 'Barbería Ñandú', 'barberia-nandu'),
            aListedBusiness($this->staffedBusinessId, 'Salón Aurora', 'salon-aurora'),
        ]);

        $useCase = ($this->useCaseFor)([
            $this->accountId => [$this->ownedBusinessId, $this->staffedBusinessId],
        ]);

        $listed = $useCase->handle(new ListAccountBusinessesInput($this->accountId))->value();

        expect(array_keys($listed))->toBe([0, 1])
            ->and($listed)->each->toBeInstanceOf(BusinessData::class);
    });
});

describe('an account that operates no business', function () {
    it('returns an empty list', function () {
        $this->businesses->shouldReceive('findManyByIds')->andReturn([]);

        $useCase = ($this->useCaseFor)([]);

        expect($useCase->handle(new ListAccountBusinessesInput($this->accountId))->value())->toBe([]);
    });

    it('reports success, because operating no business is not a refusal', function () {
        $this->businesses->shouldReceive('findManyByIds')->andReturn([]);

        $useCase = ($this->useCaseFor)([]);

        expect($useCase->handle(new ListAccountBusinessesInput($this->accountId))->succeeded())->toBeTrue();
    });

    it('asks the repository for no business at all rather than for every business', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->with([])->andReturn([]);
        $this->businesses->shouldNotReceive('findById');
        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('existsByName');

        $useCase = ($this->useCaseFor)([$this->anotherAccountId => [$this->ownedBusinessId]]);

        $useCase->handle(new ListAccountBusinessesInput($this->accountId));
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
    });

    it('returns nothing for an account the membership port does not know', function () {
        $this->businesses->shouldReceive('findManyByIds')->once()->with([])->andReturn([]);

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
