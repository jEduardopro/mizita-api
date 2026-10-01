<?php

declare(strict_types=1);

use App\Shared\Infrastructure\SessionBusinessSelection;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

const SELECTION_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a1';

const SELECTION_OTHER_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000e00a2';

const SELECTION_OWNED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000e00b1';

const SELECTION_JOINED_BUSINESS_UUID = '01930000-0000-7000-8000-0000000e00b2';

const SELECTION_SESSION_KEY = 'selected_business';

beforeEach(function () {
    $this->session = new Store('mizita_session', new ArraySessionHandler(120));
    $this->request = Request::create('/api/me/current-business', 'PUT');
    $this->request->setLaravelSession($this->session);
    $this->selection = new SessionBusinessSelection($this->request);
});

describe('with a session', function () {
    it('reads no selection when none was stored', function () {
        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull();
    });

    it('reads back the business remembered for the account', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBe(SELECTION_JOINED_BUSINESS_UUID);
    });

    it('stores the account alongside the business under the selected_business key', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);

        expect($this->session->get(SELECTION_SESSION_KEY))->toBe([
            'account_id' => SELECTION_ACCOUNT_UUID,
            'business_id' => SELECTION_JOINED_BUSINESS_UUID,
        ]);
    });

    it('replaces an earlier selection with the latest one', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_OWNED_BUSINESS_UUID);
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBe(SELECTION_JOINED_BUSINESS_UUID);
    });

    it('reads no selection once the account forgot it', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);

        $this->selection->forgetFor(SELECTION_ACCOUNT_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull()
            ->and($this->session->has(SELECTION_SESSION_KEY))->toBeFalse();
    });

    it('treats forgetting with nothing stored as a no-op', function () {
        $this->selection->forgetFor(SELECTION_ACCOUNT_UUID);

        expect($this->session->has(SELECTION_SESSION_KEY))->toBeFalse();
    });

    it('reads no selection when the value under the key is malformed', function (mixed $stored) {
        $this->session->put(SELECTION_SESSION_KEY, $stored);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull();
    })->with([
        'a bare business uuid' => [SELECTION_JOINED_BUSINESS_UUID],
        'no account' => [['business_id' => SELECTION_JOINED_BUSINESS_UUID]],
        'no business' => [['account_id' => SELECTION_ACCOUNT_UUID]],
        'a business that is not a string' => [['account_id' => SELECTION_ACCOUNT_UUID, 'business_id' => 12]],
    ]);
});

describe('a selection saved by another account', function () {
    beforeEach(function () {
        $this->selection->rememberFor(SELECTION_OTHER_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);
    });

    it('is not read for the caller', function () {
        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull();
    });

    it('survives the caller forgetting its own selection', function () {
        $this->selection->forgetFor(SELECTION_ACCOUNT_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_OTHER_ACCOUNT_UUID))->toBe(SELECTION_JOINED_BUSINESS_UUID);
    });

    it('is replaced when the caller remembers a selection of its own', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_OWNED_BUSINESS_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBe(SELECTION_OWNED_BUSINESS_UUID)
            ->and($this->selection->selectedBusinessIdFor(SELECTION_OTHER_ACCOUNT_UUID))->toBeNull();
    });
});

describe('without a session', function () {
    beforeEach(function () {
        $this->request = Request::create('/api/services', 'GET');
        $this->selection = new SessionBusinessSelection($this->request);
    });

    it('reads no selection', function () {
        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull();
    });

    it('remembers nothing and starts no session', function () {
        $this->selection->rememberFor(SELECTION_ACCOUNT_UUID, SELECTION_JOINED_BUSINESS_UUID);

        expect($this->selection->selectedBusinessIdFor(SELECTION_ACCOUNT_UUID))->toBeNull()
            ->and($this->request->hasSession())->toBeFalse();
    });

    it('forgets without failing', function () {
        expect(fn () => $this->selection->forgetFor(SELECTION_ACCOUNT_UUID))->not->toThrow(Throwable::class);
    });
});
