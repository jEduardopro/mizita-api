<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Infrastructure\Gateways\PhonesPhoneBook;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->attachedPhoneId = '01930000-0000-7000-8000-0000000000d1';
    $this->phones = Mockery::mock(PhoneRepository::class);

    $this->phoneBook = new PhonesPhoneBook(new AttachPhone(
        $this->phones,
        new FixedIdGenerator($this->attachedPhoneId),
        new FakeClock(OnboardingFixtures::now()),
    ));

    $this->attach = fn (): mixed => $this->phoneBook->attachToBusiness(
        OnboardingFixtures::GENERATED_BUSINESS_ID,
        OnboardingFixtures::phone(),
    );
});

describe('filing a business number', function () {
    it('files the number against the business, as a business number', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Business, OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->andReturnNull();

        $saved = null;
        $this->phones->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->attach)();

        expect($saved)->toBeInstanceOf(Phone::class)
            ->and($saved->ownerType)->toBe(PhoneOwnerType::Business)
            ->and($saved->ownerId)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($saved->number()->equals(OnboardingFixtures::phone()))->toBeTrue();
    });

    it('replaces the number the business already had rather than filing a second one', function () {
        $existing = Phone::restore(
            id: $this->attachedPhoneId,
            ownerType: PhoneOwnerType::Business,
            ownerId: OnboardingFixtures::GENERATED_BUSINESS_ID,
            number: OnboardingFixtures::phone(),
            createdAt: OnboardingFixtures::now(),
        );

        $this->phones->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->phones->shouldReceive('save')->once()->with($existing);

        ($this->attach)();

        expect($existing->number()->equals(OnboardingFixtures::phone()))->toBeTrue();
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect(($this->attach)())->toBeNull();
    });

    it('declares void on the port, which is what forbids answering with a failure', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(PhoneBook::class))->getMethods(),
        );

        expect($returnTypes)->toBe(['void'])
            ->and($returnTypes)->not->toContain(UseCaseResponse::class);
    });

    it('lets a domain failure out by throwing, so the surrounding transaction rolls back', function () {
        $failure = UseCaseFailed::with('phone_write_refused', DomainFailureKind::Conflict);

        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($failure);

        try {
            ($this->attach)();
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the phones table is gone');

        $this->phones->shouldReceive('findForOwner')->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($bug);

        expect(fn () => ($this->attach)())->toThrow($bug);
    });

    it('does not write when the read it depends on fails', function () {
        $bug = new RuntimeException('the read replica went away');

        $this->phones->shouldReceive('findForOwner')->once()->andThrow($bug);
        $this->phones->shouldNotReceive('save');

        expect(fn () => ($this->attach)())->toThrow($bug);
    });
});
