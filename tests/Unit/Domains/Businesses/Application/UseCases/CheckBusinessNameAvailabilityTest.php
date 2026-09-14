<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Application\UseCases\CheckBusinessNameAvailability;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\NameUnavailabilityReason;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\OnboardingFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->useCase = new CheckBusinessNameAvailability($this->businesses, new SlugAllocator);

    $this->answer = fn (string $name): UseCaseResponse => $this->useCase->handle(
        new CheckBusinessNameAvailabilityInput($name),
    );

    $this->ask = fn (string $name): NameAvailability => ($this->answer)($name)->value();

    $this->refuse = function (string $name): UseCaseError {
        $response = ($this->answer)($name);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('a name that is free', function () {
    it('says yes and shows the address the name would get', function () {
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(OnboardingFixtures::NAME)->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->once()
            ->with(OnboardingFixtures::SLUG)->andReturn([]);

        $answer = ($this->ask)(OnboardingFixtures::NAME);

        expect($answer)->toBeInstanceOf(NameAvailability::class)
            ->and($answer->available)->toBeTrue()
            ->and($answer->slug)->toBe('barberia-nandu')
            ->and($answer->reason)->toBeNull();
    });

    it('shows the numbered address when the base one is spoken for', function () {
        $this->businesses->shouldReceive('existsByName')->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->once()
            ->with(OnboardingFixtures::SLUG)->andReturn([OnboardingFixtures::SLUG]);

        $answer = ($this->ask)(OnboardingFixtures::NAME);

        expect($answer->available)->toBeTrue()
            ->and($answer->slug)->toBe('barberia-nandu-2');
    });

    it('trims the name before it asks about it', function () {
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(OnboardingFixtures::NAME)->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->andReturn([]);

        expect(($this->ask)('   Barbería Ñandú   ')->slug)->toBe('barberia-nandu');
    });
});

describe('a name that is taken', function () {
    it('says no, with the reason the API answers with', function () {
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(OnboardingFixtures::NAME)->andReturn(true);

        $answer = ($this->ask)(OnboardingFixtures::NAME);

        expect($answer->available)->toBeFalse()
            ->and($answer->reason)->toBe(NameUnavailabilityReason::Taken)
            ->and($answer->reason->value)->toBe('taken')
            ->and($answer->slug)->toBeNull();
    });

    it('does not go looking for an address it will not offer', function () {
        $this->businesses->shouldReceive('existsByName')->andReturn(true);
        $this->businesses->shouldNotReceive('slugsMatching');

        ($this->ask)(OnboardingFixtures::NAME);
    });
});

describe('a name that produces no address at all', function () {
    it('says no, and says that writing it in letters and digits is the fix', function (string $name) {
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');

        $answer = ($this->ask)($name);

        expect($answer->available)->toBeFalse()
            ->and($answer->reason)->toBe(NameUnavailabilityReason::NotSluggable)
            ->and($answer->reason->value)->toBe('not_sluggable')
            ->and($answer->slug)->toBeNull();
    })->with([
        'punctuation only' => '!!! ???',
        'a script the alphabet does not cover' => '北京 沙龙',
        'emoji' => '💇💇',
        'a reserved word' => 'Admin',
        'a reserved word padded' => '  login  ',
    ]);
});

describe('a name the length bounds refuse before an address is even considered', function () {
    it('refuses it without asking the repository anything', function (string $name) {
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');

        expect(($this->refuse)($name)->code)->toBe('invalid_business_name');
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'one character' => 'B',
        'one character padded' => '  B  ',
        'one emoji' => '💇',
        'longer than the column allows' => str_repeat('a', 121),
    ]);

    it('says a blank name is empty rather than short, because the advice differs', function () {
        $this->businesses->shouldNotReceive('existsByName');

        $error = ($this->refuse)('   ');

        expect($error->code)->toBe('invalid_business_name')
            ->and($error->cause()->getMessage())->toBe('A business name cannot be empty.');
    });

    it('names the offending length in the refusal it hands back', function () {
        $this->businesses->shouldNotReceive('existsByName');

        $error = ($this->refuse)('B');

        expect($error->code)->toBe('invalid_business_name')
            ->and($error->cause()->getMessage())->toBe('[B] is too short for a business name.');
    });

    it('classifies the refusal as invalid, which is the 422 the client sees', function () {
        $this->businesses->shouldNotReceive('existsByName');

        expect(($this->refuse)('')->kind)->toBe(DomainFailureKind::Invalid);
    });
});

describe('the failure path', function () {
    it('answers with a failure instead of throwing when a domain rule refuses', function () {
        $this->businesses->shouldNotReceive('existsByName');

        $response = ($this->answer)('');

        expect($response->failed())->toBeTrue()
            ->and($response->succeeded())->toBeFalse()
            ->and($response->error()->code)->toBe('invalid_business_name')
            ->and($response->error()->cause())->toBeInstanceOf(InvalidBusinessName::class);
    });

    it('turns a domain failure raised by a collaborator into a failure response', function () {
        $conflict = BusinessNameAlreadyTaken::for(OnboardingFixtures::NAME);
        $this->businesses->shouldReceive('existsByName')->once()->andThrow($conflict);

        $response = ($this->answer)(OnboardingFixtures::NAME);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_name_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBe($conflict);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new RuntimeException('the read replica went away');
        $this->businesses->shouldReceive('existsByName')->once()->andThrow($bug);

        try {
            ($this->answer)(OnboardingFixtures::NAME);
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($bug);
    });

    it('rethrows the original exception when the caller unwraps a failure', function () {
        $conflict = BusinessNameAlreadyTaken::for(OnboardingFixtures::NAME);
        $this->businesses->shouldReceive('existsByName')->once()->andThrow($conflict);

        $response = ($this->answer)(OnboardingFixtures::NAME);

        expect(fn () => $response->value())->toThrow($conflict);
    });
});

describe('an unavailable name is an answer, not a failure', function () {
    it('succeeds when the name is taken', function () {
        $this->businesses->shouldReceive('existsByName')->once()->andReturn(true);

        $response = ($this->answer)(OnboardingFixtures::NAME);

        expect($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->value()->reason)->toBe(NameUnavailabilityReason::Taken);
    });

    it('succeeds when the name yields no address', function () {
        $this->businesses->shouldNotReceive('existsByName');

        $response = ($this->answer)('北京 沙龙');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->reason)->toBe(NameUnavailabilityReason::NotSluggable);
    });

    it('carries no warnings on any of the three answers', function (string $name, bool $consulted) {
        if ($consulted) {
            $this->businesses->shouldReceive('existsByName')->andReturn(false);
            $this->businesses->shouldReceive('slugsMatching')->andReturn([]);
        }

        expect(($this->answer)($name)->warnings())->toBe([]);
    })->with([
        'available' => [OnboardingFixtures::NAME, true],
        'not sluggable' => ['北京 沙龙', false],
    ]);
});

describe('the contract that it answers rather than throws, for every name the edge lets through', function () {
    it('answers any name within the bounds the form request already enforces', function (string $name) {
        $this->businesses->shouldReceive('existsByName')->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->andReturn([]);

        expect(($this->ask)($name))->toBeInstanceOf(NameAvailability::class);
    })->with([
        'punctuation' => '@@@',
        'reserved' => 'dashboard',
        'accented' => 'Barbería Ñandú',
        'the shortest name allowed' => 'Bo',
        'the longest name allowed' => str_repeat('a', 120),
        'very long' => 'Barbería La Esquina de Don José Luis Martínez en el Centro Histórico de la Ciudad',
        'a LIKE wildcard' => '100% Barbería_Ñandú',
        'another script' => 'Салон Красоты',
    ]);

    it('answers a name with no usable address instead of failing, which is why Slug has a nullable twin', function () {
        $this->businesses->shouldNotReceive('existsByName');

        expect(($this->ask)('北京 沙龙')->reason)->toBe(NameUnavailabilityReason::NotSluggable);
    });

    it('is advisory: a free answer is not a reservation', function () {
        $this->businesses->shouldReceive('existsByName')->twice()->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->twice()->andReturn([]);
        $this->businesses->shouldNotReceive('save');

        expect(($this->ask)(OnboardingFixtures::NAME)->slug)
            ->toBe(($this->ask)(OnboardingFixtures::NAME)->slug);
    });
});
