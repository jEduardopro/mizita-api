<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Search;

use App\Shared\ValueObjects\AccentFolding;
use App\Shared\ValueObjects\SearchTerm;

final class SearchExpression
{
    private const FOLDED_CONTAINS = "lower(translate(%s, ?, ?)) like '%%' || ? || '%%'";

    private const NUMERIC_EQUALS = '%s = cast(? as numeric)';

    private const ANY_SEPARATOR = ' or ';

    private const NEVER_MATCHES = 'false';

    private const MATCHED_TOKEN = 'case when %s then 1 else 0 end';

    private const SCORE_SEPARATOR = ' + ';

    private const LIKE_METACHARACTERS = ['\\', '%', '_'];

    private const ESCAPED_METACHARACTERS = ['\\\\', '\\%', '\\_'];

    public function matches(SearchTerm $term, SearchableColumns $columns): SqlFragment
    {
        $clauses = [];
        $bindings = [];

        foreach ($term->tokens() as $token) {
            $match = $this->matchesToken($token, $columns);

            $clauses[] = $match->sql;
            $bindings = [...$bindings, ...$match->bindings];
        }

        return new SqlFragment(self::anyOf($clauses), $bindings);
    }

    public function relevance(SearchTerm $term, SearchableColumns $columns): SqlFragment
    {
        $scores = [];
        $bindings = [];

        foreach ($term->tokens() as $token) {
            $match = $this->matchesToken($token, $columns);

            $scores[] = sprintf(self::MATCHED_TOKEN, $match->sql);
            $bindings = [...$bindings, ...$match->bindings];
        }

        return new SqlFragment(self::sumOf($scores), $bindings);
    }

    private function matchesToken(string $token, SearchableColumns $columns): SqlFragment
    {
        $clauses = [];
        $bindings = [];
        $escaped = self::escapeLike($token);

        foreach ($columns->textColumns() as $column) {
            $clauses[] = sprintf(self::FOLDED_CONTAINS, $column);
            $bindings[] = AccentFolding::sourceCharacters();
            $bindings[] = AccentFolding::replacementCharacters();
            $bindings[] = $escaped;
        }

        if (is_numeric($token)) {
            foreach ($columns->numericColumns() as $column) {
                $clauses[] = sprintf(self::NUMERIC_EQUALS, $column);
                $bindings[] = $token;
            }
        }

        return new SqlFragment(self::anyOf($clauses), $bindings);
    }

    /**
     * @param  list<string>  $clauses
     */
    private static function anyOf(array $clauses): string
    {
        if ($clauses === []) {
            return self::NEVER_MATCHES;
        }

        return '('.implode(self::ANY_SEPARATOR, $clauses).')';
    }

    /**
     * @param  list<string>  $scores
     */
    private static function sumOf(array $scores): string
    {
        return implode(self::SCORE_SEPARATOR, $scores);
    }

    private static function escapeLike(string $token): string
    {
        return str_replace(self::LIKE_METACHARACTERS, self::ESCAPED_METACHARACTERS, $token);
    }
}
