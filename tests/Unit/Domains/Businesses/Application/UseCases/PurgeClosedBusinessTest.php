<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\PurgeClosedBusinessInput;
use App\Domains\Businesses\Application\UseCases\PurgeClosedBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\TenantDataEraser;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\ClosureFixtures;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->eraser = Mockery::mock(TenantDataEraser::class);
    $this->transactions = new FakeTransactionManager;

    $this->purgeAt = function (string $now) {
        return (new PurgeClosedBusiness(
            $this->businesses,
            $this->eraser,
            new FakeClock(new DateTimeImmutable($now)),
            $this->transactions,
        ))->handle(new PurgeClosedBusinessInput(OnboardingFixtures::GENERATED_BUSINESS_ID));
    };

    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };
});

describe('purging a business whose retention has ended', function () {
    beforeEach(function () {
        $this->closed = ClosureFixtures::closedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturn($this->closed);
    });

    it('erases the files and the records of that business and saves it purged at the clock instant', function () {
        $saved = null;
        $this->eraser->shouldReceive('eraseFilesOf')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->eraser->shouldReceive('eraseRecordsOf')->once()->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $response = ($this->purgeAt)('2026-03-04T03:00:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($saved)->toBe($this->closed)
            ->and($saved->isPurged())->toBeTrue()
            ->and($saved->purgedAt())->toEqual(new DateTimeImmutable('2026-03-04T03:00:00+00:00'))
            ->and($saved->isClosed())->toBeTrue();
    });

    it('purges at the exact instant the retention ends', function () {
        $this->eraser->shouldReceive('eraseFilesOf')->once();
        $this->eraser->shouldReceive('eraseRecordsOf')->once();
        $this->businesses->shouldReceive('save')->once();

        expect(($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT)->succeeded())->toBeTrue()
            ->and($this->closed->purgedAt())->toEqual(ClosureFixtures::purgeDueAt());
    });

    it('erases the files before the records, and saves last', function () {
        $order = [];
        $record = function (string $step) use (&$order): bool {
            $order[] = $step;

            return true;
        };

        $this->eraser->shouldReceive('eraseFilesOf')->once()->with(Mockery::on(fn (): bool => $record('files')));
        $this->eraser->shouldReceive('eraseRecordsOf')->once()->with(Mockery::on(fn (): bool => $record('records')));
        $this->businesses->shouldReceive('save')->once()->with(Mockery::on(fn (): bool => $record('save')));

        ($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT);

        expect($order)->toBe(['files', 'records', 'save']);
    });

    it('erases the files outside the transaction and the records with the save inside it', function () {
        $this->eraser->shouldReceive('eraseFilesOf')->once()->with(Mockery::on($this->recordTransactionState));
        $this->eraser->shouldReceive('eraseRecordsOf')->once()->with(Mockery::on($this->recordTransactionState));
        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));

        ($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT);

        expect($this->insideTransaction)->toBe([false, true, true])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('opens no transaction and saves nothing when erasing the files fails', function () {
        $outage = new RuntimeException('the media disk went away');
        $this->eraser->shouldReceive('eraseFilesOf')->once()->andThrow($outage);
        $this->eraser->shouldNotReceive('eraseRecordsOf');
        $this->businesses->shouldNotReceive('save');

        expect(fn () => ($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT))->toThrow($outage);

        expect($this->transactions->runs())->toBe(0);
    });

    it('never saves the purge when erasing the records fails', function () {
        $outage = new RuntimeException('a foreign key held a row');
        $this->eraser->shouldReceive('eraseFilesOf')->once();
        $this->eraser->shouldReceive('eraseRecordsOf')->once()->andThrow($outage);
        $this->businesses->shouldNotReceive('save');

        expect(fn () => ($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT))->toThrow($outage);
    });
});

describe('refusing to purge', function () {
    beforeEach(function () {
        $this->eraser->shouldNotReceive('eraseFilesOf');
        $this->eraser->shouldNotReceive('eraseRecordsOf');
        $this->businesses->shouldNotReceive('save');
    });

    it('answers a conflict one second before the retention ends, erasing nothing', function () {
        $closed = ClosureFixtures::closedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()->andReturn($closed);

        $response = ($this->purgeAt)(ClosureFixtures::ONE_SECOND_BEFORE_PURGE_DUE);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_due_for_purge')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($closed->isPurged())->toBeFalse()
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers a conflict for a business that is not closed, erasing nothing', function () {
        $this->businesses->shouldReceive('findClosedById')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID)->andReturnNull();

        $response = ($this->purgeAt)(ClosureFixtures::PURGE_DUE_AT);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->transactions->runs())->toBe(0);
    });
});

describe('a business already purged', function () {
    it('succeeds without erasing or saving anything, keeping the original purge instant', function () {
        $purged = ClosureFixtures::purgedBusiness();
        $this->businesses->shouldReceive('findClosedById')->once()->andReturn($purged);
        $this->eraser->shouldNotReceive('eraseFilesOf');
        $this->eraser->shouldNotReceive('eraseRecordsOf');
        $this->businesses->shouldNotReceive('save');

        $response = ($this->purgeAt)('2026-06-01T03:00:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($purged->purgedAt())->toEqual(ClosureFixtures::purgeDueAt())
            ->and($this->transactions->runs())->toBe(0);
    });
});
