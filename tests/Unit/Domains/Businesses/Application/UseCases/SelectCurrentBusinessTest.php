<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\SelectCurrentBusinessInput;
use App\Domains\Businesses\Application\UseCases\SelectCurrentBusiness;
use App\Domains\Businesses\Exceptions\InvalidBusinessSelection;
use App\Http\Exceptions\BusinessAccessDenied;
use App\Http\Exceptions\TeamAccessPaused;
use App\Shared\Contracts\BusinessSelection;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeCurrentBusinessResolver;

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';
    $this->anotherAccountId = '01930000-0000-7000-8000-0000000000a2';
    $this->businessId = '01930000-0000-7000-8000-000000000002';

    $this->resolver = new FakeCurrentBusinessResolver;
    $this->selection = Mockery::mock(BusinessSelection::class);

    $this->select = fn (SelectCurrentBusinessInput $input) => (new SelectCurrentBusiness($this->resolver, $this->selection))
        ->handle($input);
});

describe('switching to a business the account may operate', function () {
    it('succeeds and carries no data and no warning', function () {
        $this->selection->shouldReceive('rememberFor')->once();

        $response = ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($response->warnings())->toBe([]);
    });

    it('remembers the business for the account, exactly once', function () {
        $this->selection->shouldReceive('rememberFor')->once()->with($this->accountId, $this->businessId);

        ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));
    });

    it('asks the resolver whether the account may operate the business it named', function () {
        $this->selection->shouldReceive('rememberFor')->once();

        ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));

        expect($this->resolver->resolutions)->toBe([
            ['accountId' => $this->accountId, 'requestedBusinessId' => $this->businessId],
        ]);
    });

    it('remembers the business the resolver vouched for', function () {
        $vouchedFor = '01930000-0000-7000-8000-000000000001';
        $this->resolver = new FakeCurrentBusinessResolver($vouchedFor);
        $this->selection->shouldReceive('rememberFor')->once()->with($this->accountId, $vouchedFor);

        ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));
    });

    it('remembers the selection under the account the input carries', function () {
        $this->selection->shouldReceive('rememberFor')->once()->with($this->anotherAccountId, $this->businessId);

        ($this->select)(new SelectCurrentBusinessInput($this->anotherAccountId, $this->businessId));
    });

    it('resolves the normalised id a padded uppercase payload produced', function () {
        $this->selection->shouldReceive('rememberFor')->once()->with($this->accountId, $this->businessId);

        ($this->select)(SelectCurrentBusinessInput::fromRequest(
            ['business_id' => ' '.strtoupper($this->businessId).' '],
            $this->accountId,
        ));

        expect($this->resolver->resolutions[0]['requestedBusinessId'])->toBe($this->businessId);
    });

    it('never forgets a selection while making one', function () {
        $this->selection->shouldReceive('rememberFor')->once();
        $this->selection->shouldNotReceive('forgetFor');

        ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));
    });
});

describe('an input that names no usable business', function () {
    it('returns the validation failure', function (string $businessId) {
        $this->selection->shouldNotReceive('rememberFor');

        $response = ($this->select)(new SelectCurrentBusinessInput($this->accountId, $businessId));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_business_selection')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause())->toBeInstanceOf(InvalidBusinessSelection::class);
    })->with([
        'missing' => '',
        'malformed' => 'barberia-nandu',
    ]);

    it('never reaches the resolver', function () {
        $this->selection->shouldNotReceive('rememberFor');

        ($this->select)(SelectCurrentBusinessInput::fromRequest([], $this->accountId));

        expect($this->resolver->resolutions)->toBe([]);
    });
});

describe('a business the resolver refuses', function () {
    it('returns the refusal and remembers nothing', function (Throwable $refusal, string $code) {
        $this->resolver->refusingWith($refusal);
        $this->selection->shouldNotReceive('rememberFor');
        $this->selection->shouldNotReceive('forgetFor');

        $response = ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($response->error()->cause())->toBe($refusal);
    })->with([
        'a business the account cannot access' => [
            fn () => BusinessAccessDenied::businessNotAccessible('01930000-0000-7000-8000-000000000002'),
            'business_not_accessible',
        ],
        'a business whose team access is paused' => [
            fn () => TeamAccessPaused::forBusiness('01930000-0000-7000-8000-000000000002'),
            'team_access_paused',
        ],
        'an account with no business at all' => [
            fn () => BusinessAccessDenied::accountHasNoBusiness(),
            'no_business',
        ],
    ]);

    it('lets a resolver error that is not a refusal escape', function () {
        $this->resolver->refusingWith(new RuntimeException('session store unavailable'));
        $this->selection->shouldNotReceive('rememberFor');

        expect(fn () => ($this->select)(new SelectCurrentBusinessInput($this->accountId, $this->businessId)))
            ->toThrow(RuntimeException::class, 'session store unavailable');
    });
});
