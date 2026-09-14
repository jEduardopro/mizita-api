<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Application\UseCases\CheckBusinessNameAvailability;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\NameUnavailabilityReason;
use Tests\Support\Businesses\OnboardingFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->useCase = new CheckBusinessNameAvailability($this->businesses, new SlugAllocator);

    $this->ask = fn (string $name): NameAvailability => $this->useCase->handle(
        new CheckBusinessNameAvailabilityInput($name),
    );
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

        expect(fn () => ($this->ask)($name))->toThrow(InvalidBusinessName::class);
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

        expect(fn () => ($this->ask)('   '))
            ->toThrow(InvalidBusinessName::class, 'A business name cannot be empty.');
    });

    it('names the offending length in the refusal it hands back', function () {
        $this->businesses->shouldNotReceive('existsByName');

        expect(fn () => ($this->ask)('B'))
            ->toThrow(InvalidBusinessName::class, '[B] is too short for a business name.');
    });
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
