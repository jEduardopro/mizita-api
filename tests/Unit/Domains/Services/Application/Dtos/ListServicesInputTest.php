<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\InvalidServiceSearch;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\Services\ServiceFixtures;

describe('reading a query string', function () {
    it('assembles itself from a well formed query string', function () {
        $input = ListServicesInput::fromRequest([
            'search' => 'corte',
            'sort' => 'price',
            'direction' => 'desc',
            'page' => 3,
            'per_page' => 25,
        ]);

        expect($input->search)->toBe('corte')
            ->and($input->sort)->toBe('price')
            ->and($input->direction)->toBe('desc')
            ->and($input->page)->toBe(3)
            ->and($input->perPage)->toBe(25);
    });

    it('survives a query string with every key missing', function () {
        $input = ListServicesInput::fromRequest([]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads the numbers a query string carries as strings', function () {
        $input = ListServicesInput::fromRequest(['page' => '2', 'per_page' => '50']);

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(50);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(ListServicesInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'search as an array' => [['search' => ['corte']], 'search'],
        'sort as an array' => [['sort' => ['name']], 'sort'],
        'page as a word' => [['page' => 'first'], 'page'],
        'page as an array' => [['page' => [2]], 'page'],
        'per page as a word' => [['per_page' => 'all'], 'perPage'],
    ]);

    it('reads the staff ids to filter by in the order they were given', function () {
        $input = ListServicesInput::fromRequest([
            'staff_ids' => [ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID],
        ]);

        expect($input->staffIds)->toBe([ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID]);
    });

    it('filters by no staff when the query string names none', function () {
        expect(ListServicesInput::fromRequest([])->staffIds)->toBe([]);
    });

    it('reads staff ids that are not a list as no filter at all', function (mixed $staffIds) {
        expect(ListServicesInput::fromRequest(['staff_ids' => $staffIds])->staffIds)->toBe([]);
    })->with([
        'null' => null,
        'a single uuid as a string' => ServiceFixtures::STAFF_ID,
        'a comma separated string' => ServiceFixtures::STAFF_ID.','.ServiceFixtures::SECOND_STAFF_ID,
        'a number' => 7,
        'a boolean' => true,
    ]);

    it('reads a staff id that is not a string as a blank one, so validation refuses it', function (mixed $staffId) {
        expect(ListServicesInput::fromRequest(['staff_ids' => [ServiceFixtures::STAFF_ID, $staffId]])->staffIds)
            ->toBe([ServiceFixtures::STAFF_ID, '']);
    })->with([
        'null' => null,
        'a number' => 42,
        'a nested list' => [[ServiceFixtures::SECOND_STAFF_ID]],
        'a boolean' => false,
    ]);

    it('reindexes the staff ids into a list whatever keys they arrived with', function () {
        $input = ListServicesInput::fromRequest([
            'staff_ids' => [3 => ServiceFixtures::STAFF_ID, 'second' => ServiceFixtures::SECOND_STAFF_ID],
        ]);

        expect($input->staffIds)->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID])
            ->and(array_is_list($input->staffIds))->toBeTrue();
    });
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => ListServicesInput::fromRequest(['search' => 'corte'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string, because a list needs no arguments', function () {
        expect(fn () => ListServicesInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a search term as long as it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a search term longer than it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH + 1),
        ])->validate())->toThrow(InvalidServiceSearch::class);
    });

    it('measures a search term after trimming it', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => '   '.str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH).'   ',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('never refuses a navigation value, however absurd', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'page' => -9999,
            'per_page' => 9999,
            'sort' => 'whatever',
            'direction' => 'sideways',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('filters by at most as many staff as a service may carry', function () {
        expect(ListServicesInput::MAXIMUM_STAFF_FILTER_SIZE)->toBe(Service::MAXIMUM_STAFF_MEMBERS);
    });

    it('accepts a staff filter as large as it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'staff_ids' => distinctStaffIds(ListServicesInput::MAXIMUM_STAFF_FILTER_SIZE),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a staff id whatever the case of its hexadecimal', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'staff_ids' => [strtoupper(ServiceFixtures::STAFF_ID)],
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a staff filter larger than it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'staff_ids' => distinctStaffIds(ListServicesInput::MAXIMUM_STAFF_FILTER_SIZE + 1),
        ])->validate())->toThrow(UnknownStaffMember::class);
    });

    it('refuses a staff filter it cannot trust', function (array $staffIds) {
        expect(fn () => ListServicesInput::fromRequest(['staff_ids' => $staffIds])->validate())
            ->toThrow(UnknownStaffMember::class);
    })->with([
        'a malformed uuid' => [['staff-1']],
        'a uuid with a trailing newline' => [[ServiceFixtures::STAFF_ID."\n"]],
        'a uuid missing a character' => [[substr(ServiceFixtures::STAFF_ID, 0, -1)]],
        'a uuid with no dashes' => [[str_replace('-', '', ServiceFixtures::STAFF_ID)]],
        'an empty string' => [['']],
        'whitespace only' => [['   ']],
        'a valid id next to a malformed one' => [[ServiceFixtures::STAFF_ID, 'not-a-uuid']],
        'a non string element' => [[ServiceFixtures::STAFF_ID, 42]],
        'the same id twice' => [[ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID]],
    ]);

    it('refuses the staff filter with a failure the caller can be told about', function () {
        $refusal = null;

        try {
            ListServicesInput::fromRequest(['staff_ids' => ['staff-1']])->validate();
        } catch (UnknownStaffMember $failure) {
            $refusal = $failure;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('unknown_staff_member')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('turning itself into a query', function () {
    it('carries the search, the sort, the direction and the pagination it was given', function () {
        $query = ListServicesInput::fromRequest([
            'search' => 'corte',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 2,
            'per_page' => 25,
        ])->toQuery();

        expect($query->search)->toBeInstanceOf(SearchTerm::class)
            ->and($query->search->tokens())->toBe(['corte'])
            ->and($query->search->raw())->toBe('corte')
            ->and($query->sort)->toBe(ServiceSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(2)
            ->and($query->pagination->perPage)->toBe(25);
    });

    it('falls back to a sort it serves instead of refusing an unknown one', function (?string $sort) {
        expect(ListServicesInput::fromRequest(['sort' => $sort])->toQuery()->sort)
            ->toBe(ServiceSort::Name);
    })->with([
        'missing' => null,
        'unknown' => 'whatever',
        'a column it does not expose' => 'business_id',
        'uppercase' => 'NAME',
        'empty' => '',
    ]);

    it('falls back to ascending instead of refusing an unknown direction', function (?string $direction) {
        expect(ListServicesInput::fromRequest(['direction' => $direction])->toQuery()->direction)
            ->toBe(SortDirection::Ascending);
    })->with(['missing' => null, 'unknown' => 'sideways', 'uppercase' => 'DESC', 'empty' => '']);

    it('clamps the pagination instead of refusing it', function () {
        $query = ListServicesInput::fromRequest(['page' => 0, 'per_page' => 9999])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });

    it('paginates by default when nothing was asked for', function () {
        $query = ListServicesInput::fromRequest([])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('trims the search term it passes down', function () {
        $search = ListServicesInput::fromRequest(['search' => '  corte  '])->toQuery()->search;

        expect($search->raw())->toBe('corte')
            ->and($search->tokens())->toBe(['corte']);
    });

    it('splits the search term into the words it will look for', function () {
        expect(ListServicesInput::fromRequest(['search' => 'up tes'])->toQuery()->search->tokens())
            ->toBe(['up', 'tes']);
    });

    it('hands down the words already folded, in the spelling the database is searched with', function () {
        $search = ListServicesInput::fromRequest(['search' => 'Depilación LÁSER'])->toQuery()->search;

        expect($search->tokens())->toBe(['depilacion', 'laser'])
            ->and($search->raw())->toBe('Depilación LÁSER');
    });

    it('drops the units a caller typed alongside a number', function () {
        expect(ListServicesInput::fromRequest(['search' => 'corte 30 min'])->toQuery()->search->tokens())
            ->toBe(['corte', '30']);
    });

    it('carries no search term when every word it was given is a unit', function () {
        expect(ListServicesInput::fromRequest(['search' => 'min horas'])->toQuery()->search)->toBeNull();
    });

    it('carries no search term when the one it was given is blank', function (?string $search) {
        expect(ListServicesInput::fromRequest(['search' => $search])->toQuery()->search)->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('carries the staff ids to filter by, in the order they were given', function () {
        $query = ListServicesInput::fromRequest([
            'staff_ids' => [ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID],
        ])->toQuery();

        expect($query->staffIds)->toBe([ServiceFixtures::SECOND_STAFF_ID, ServiceFixtures::STAFF_ID]);
    });

    it('carries no staff filter when none was asked for', function () {
        expect(ListServicesInput::fromRequest([])->toQuery()->staffIds)->toBe([]);
    });

    it('carries the staff ids of an input built without a request', function () {
        $query = (new ListServicesInput(null, null, null, null, null, [ServiceFixtures::STAFF_ID]))->toQuery();

        expect($query->staffIds)->toBe([ServiceFixtures::STAFF_ID]);
    });
});

/**
 * @return list<string>
 */
function distinctStaffIds(int $count): array
{
    return array_map(
        static fn (int $n): string => sprintf('01930000-0000-7000-8000-%012d', $n),
        range(1, $count),
    );
}
