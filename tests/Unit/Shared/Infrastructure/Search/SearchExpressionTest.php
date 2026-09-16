<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Search\SearchableColumns;
use App\Shared\Infrastructure\Search\SearchExpression;
use App\Shared\Infrastructure\Search\SqlFragment;
use App\Shared\ValueObjects\AccentFolding;
use App\Shared\ValueObjects\SearchTerm;

beforeEach(function () {
    $this->expression = new SearchExpression;
    $this->columns = SearchableColumns::text('name', 'description')
        ->alsoMatchingNumeric('duration_minutes', 'price');
    $this->term = fn (string $raw): SearchTerm => SearchTerm::of($raw);
});

describe('finding the rows a phrase matches', function () {
    it('compiles a word into a folded containment test for each text column', function () {
        $matches = $this->expression->matches(($this->term)('corte'), SearchableColumns::text('name', 'description'));

        expect($matches)->toBeInstanceOf(SqlFragment::class)
            ->and($matches->sql)->toBe(
                "((lower(translate(name, ?, ?)) like '%' || ? || '%'".
                " or lower(translate(description, ?, ?)) like '%' || ? || '%'))"
            );
    });

    it('binds the folding map alongside the word, once per text column', function () {
        $matches = $this->expression->matches(($this->term)('corte'), SearchableColumns::text('name', 'description'));

        expect($matches->bindings)->toBe([
            AccentFolding::sourceCharacters(),
            AccentFolding::replacementCharacters(),
            'corte',
            AccentFolding::sourceCharacters(),
            AccentFolding::replacementCharacters(),
            'corte',
        ]);
    });

    it('searches for the word already folded, so an accented row still matches', function () {
        $matches = $this->expression->matches(($this->term)('LÁSER'), SearchableColumns::text('name'));

        expect($matches->bindings[2])->toBe('laser');
    });

    it('matches a row carrying any one of the words, never only a row carrying all of them', function () {
        $sql = $this->expression->matches(($this->term)('new upd'), $this->columns)->sql;

        expect($sql)->toContain(') or (')
            ->and($sql)->not->toContain(' and ')
            ->and(substr_count($sql, ' or '))->toBe(3)
            ->and(substr_count($sql, 'like'))->toBe(4);
    });

    it('binds every word it was given', function () {
        $bindings = $this->expression->matches(($this->term)('new upd'), $this->columns)->bindings;

        expect($bindings)->toContain('new')
            ->and($bindings)->toContain('upd')
            ->and($bindings)->toHaveCount(12);
    });
});

describe('reading a number as a price or a duration', function () {
    it('compares a number against each numeric column as well as the text ones', function () {
        $matches = $this->expression->matches(($this->term)('30'), $this->columns);

        expect($matches->sql)->toContain('duration_minutes = cast(? as numeric)')
            ->and($matches->sql)->toContain('price = cast(? as numeric)')
            ->and(array_slice($matches->bindings, -2))->toBe(['30', '30']);
    });

    it('never compares a word to a numeric column', function () {
        $matches = $this->expression->matches(($this->term)('corte'), $this->columns);

        expect($matches->sql)->not->toContain('cast(')
            ->and($matches->bindings)->toHaveCount(6);
    });

    it('reads only the number of a phrase as a number', function () {
        $sql = $this->expression->matches(($this->term)('corte 30'), $this->columns)->sql;

        expect(substr_count($sql, 'cast(? as numeric)'))->toBe(2);
    });
});

describe('escaping what a caller typed', function () {
    it('escapes the like metacharacters before binding the word', function (string $raw, string $bound) {
        $matches = $this->expression->matches(($this->term)($raw), SearchableColumns::text('name'));

        expect($matches->bindings[2])->toBe($bound);
    })->with([
        'a percent sign' => ['100%', '100\%'],
        'an underscore' => ['a_b', 'a\_b'],
        'a backslash' => ['a\\b', 'a\\\\b'],
        'every metacharacter at once' => ['a%_\\b', 'a\\%\\_\\\\b'],
    ]);
});

describe('scoring how well a row matches', function () {
    it('counts one point per word the row carries', function () {
        $relevance = $this->expression->relevance(($this->term)('new upd'), $this->columns);

        expect(substr_count($relevance->sql, 'case when '))->toBe(2)
            ->and(substr_count($relevance->sql, ' then 1 else 0 end'))->toBe(2)
            ->and(substr_count($relevance->sql, ' + '))->toBe(1);
    });

    it('scores a single word without summing anything', function () {
        $relevance = $this->expression->relevance(($this->term)('corte'), $this->columns);

        expect(substr_count($relevance->sql, 'case when '))->toBe(1)
            ->and($relevance->sql)->not->toContain(' + ');
    });

    it('scores each word against the same clause it matches on', function () {
        $term = ($this->term)('new upd');

        expect($this->expression->relevance($term, $this->columns)->bindings)
            ->toBe($this->expression->matches($term, $this->columns)->bindings);
    });
});

describe('being given nowhere to look', function () {
    it('matches nothing rather than emitting broken sql when no column applies to the word', function () {
        $matches = $this->expression->matches(($this->term)('corte'), SearchableColumns::text()->alsoMatchingNumeric('price'));

        expect($matches->sql)->toBe('(false)')
            ->and($matches->bindings)->toBe([]);
    });

    it('scores every row alike rather than emitting broken sql when no column applies', function () {
        $relevance = $this->expression->relevance(($this->term)('corte'), SearchableColumns::text());

        expect($relevance->sql)->toBe('case when false then 1 else 0 end')
            ->and($relevance->bindings)->toBe([]);
    });

    it('matches on the numeric column alone when there is no text column to search', function () {
        $matches = $this->expression->matches(($this->term)('30'), SearchableColumns::text()->alsoMatchingNumeric('price'));

        expect($matches->sql)->toBe('((price = cast(? as numeric)))')
            ->and($matches->bindings)->toBe(['30']);
    });
});
