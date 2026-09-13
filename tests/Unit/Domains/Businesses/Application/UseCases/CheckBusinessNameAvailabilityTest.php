<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Application\UseCases\CheckBusinessNameAvailability;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\NameUnavailabilityReason;
use Tests\Support\Businesses\OnboardingFixtures;

/*
| Built from mocks alone. The form asks this on every pause in typing, so the
| contract is that it answers - it never throws, whatever it is handed, and the
| last describe() in this file is the one that holds that down.
*/

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->useCase = new CheckBusinessNameAvailability($this->businesses, new SlugAllocator);

    $this->ask = fn (string $name): NameAvailability => $this->useCase->handle(
        new CheckBusinessNameAvailabilityInput($name),
    );
});

describe('a name that is free', function () {
    it('says yes and shows the address the name would get', function () {
        // The slug is part of the answer because somebody choosing a name
        // deserves to see the address it gives them before they commit.
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
        // The name is free but the address is not, which happens whenever a
        // name folds onto an existing slug.
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
            // No address is offered for a name nobody can use.
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
        // Different advice from "taken": one says pick another name, the other
        // says write this one differently.
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');

        $answer = ($this->ask)($name);

        expect($answer->available)->toBeFalse()
            ->and($answer->reason)->toBe(NameUnavailabilityReason::NotSluggable)
            ->and($answer->reason->value)->toBe('not_sluggable')
            ->and($answer->slug)->toBeNull();
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'punctuation only' => '!!! ???',
        'a script the alphabet does not cover' => '北京 沙龙',
        'emoji' => '💇',
        'a reserved word' => 'Admin',
        'a reserved word padded' => '  login  ',
    ]);
});

describe('the contract that it always answers', function () {
    it('never throws, whatever it is handed', function (string $name) {
        // A refusal the caller asked for is not a failure. The whole reason
        // Slug has a nullable twin is this method.
        $this->businesses->shouldReceive('existsByName')->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->andReturn([]);

        expect(($this->ask)($name))->toBeInstanceOf(NameAvailability::class);
    })->with([
        'empty' => '',
        'one character' => 'B',
        'punctuation' => '@@@',
        'reserved' => 'dashboard',
        'accented' => 'Barbería Ñandú',
        'very long' => 'Barbería La Esquina de Don José Luis Martínez en el Centro Histórico de la Ciudad',
        'a LIKE wildcard' => '100% Barbería_Ñandú',
        'another script' => 'Салон Красоты',
    ]);

    it('is advisory: a free answer is not a reservation', function () {
        // Two people can be told the same free name at the same moment. Only
        // the partial unique index settles it, and OnboardBusiness checks again
        // on the way in - which is why nothing here writes.
        $this->businesses->shouldReceive('existsByName')->twice()->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->twice()->andReturn([]);
        $this->businesses->shouldNotReceive('save');

        expect(($this->ask)(OnboardingFixtures::NAME)->slug)
            ->toBe(($this->ask)(OnboardingFixtures::NAME)->slug);
    });
});
