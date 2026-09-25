<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ListTeamMembersInput;
use App\Domains\Staff\Exceptions\InvalidTeamSearch;
use App\Domains\Staff\ValueObjects\TeamSort;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

describe('building itself from a query string', function () {
    it('reads every parameter of a well formed query', function () {
        $input = ListTeamMembersInput::fromRequest([
            'search' => 'grace',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => '3',
            'per_page' => '50',
        ]);

        expect($input->search)->toBe('grace')
            ->and($input->sort)->toBe('created_at')
            ->and($input->direction)->toBe('desc')
            ->and($input->page)->toBe(3)
            ->and($input->perPage)->toBe(50);
    });

    it('reads every missing parameter as absent', function () {
        $input = ListTeamMembersInput::fromRequest([]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads a parameter of the wrong type as absent, never as a PHP error', function () {
        $input = ListTeamMembersInput::fromRequest([
            'search' => ['grace'],
            'sort' => 1,
            'direction' => null,
            'page' => 'two',
            'per_page' => ['50'],
        ]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });
});

describe('validating the search', function () {
    it('returns silently with no search, or a search at the limit', function (?string $search) {
        expect(fn () => (new ListTeamMembersInput($search, null, null, null, null))->validate())->not->toThrow(Throwable::class);
    })->with([
        'none' => [null],
        'empty' => [''],
        'exactly the limit' => [str_repeat('a', 120)],
        'the limit in accented letters' => [str_repeat('é', 120)],
        'the limit with padding around it' => ['   '.str_repeat('a', 120).'   '],
    ]);

    it('refuses a search one character past the limit', function () {
        $thrown = null;

        try {
            (new ListTeamMembersInput(str_repeat('a', 121), null, null, null, null))->validate();
        } catch (InvalidTeamSearch $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown?->errorCode())->toBe('invalid_team_search')
            ->and($thrown?->getMessage())->toBe('A team search may not run past 120 characters.');
    });
});

describe('the query it hands the roster', function () {
    it('sorts by name ascending on the first page of twenty when nothing was asked for', function () {
        $query = ListTeamMembersInput::fromRequest([])->toQuery();

        expect($query->search)->toBeNull()
            ->and($query->sort)->toBe(TeamSort::Name)
            ->and($query->direction)->toBe(SortDirection::Ascending)
            ->and($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE)
            ->and($query->profileIdsMatchingPhone)->toBe([]);
    });

    it('sorts by the creation instant descending when asked to', function () {
        $query = ListTeamMembersInput::fromRequest(['sort' => 'created_at', 'direction' => 'desc'])->toQuery();

        expect($query->sort)->toBe(TeamSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending);
    });

    it('falls back to the default for a sort or a direction it does not know', function () {
        $query = ListTeamMembersInput::fromRequest(['sort' => 'email', 'direction' => 'sideways'])->toQuery();

        expect($query->sort)->toBe(TeamSort::Name)
            ->and($query->direction)->toBe(SortDirection::Ascending);
    });

    it('clamps the page and the page size into range', function () {
        $query = ListTeamMembersInput::fromRequest(['page' => '0', 'per_page' => '1000'])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });

    it('drops a blank search rather than searching for nothing', function () {
        expect(ListTeamMembersInput::fromRequest(['search' => "   \t"])->toQuery()->search)->toBeNull();
    });

    it('carries the search as typed, collapsed', function () {
        expect(ListTeamMembersInput::fromRequest(['search' => '  Grace   Hopper '])->toQuery()->search?->raw())->toBe('Grace Hopper');
    });

    it('carries the profiles whose phone matched the search', function () {
        $query = ListTeamMembersInput::fromRequest(['search' => '5512'])->toQuery(['profile-1', 'profile-2']);

        expect($query->profileIdsMatchingPhone)->toBe(['profile-1', 'profile-2']);
    });
});
