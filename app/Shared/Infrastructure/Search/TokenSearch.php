<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Search;

use App\Shared\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final class TokenSearch
{
    private const MOST_RELEVANT_FIRST = '%s desc';

    public function __construct(
        private readonly SearchExpression $expression,
    ) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, SearchTerm $term, SearchableColumns $columns): Builder
    {
        $matches = $this->expression->matches($term, $columns);

        return $query->whereRaw($matches->sql, $matches->bindings);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function orderByRelevance(Builder $query, SearchTerm $term, SearchableColumns $columns): Builder
    {
        $relevance = $this->expression->relevance($term, $columns);

        return $query->orderByRaw(sprintf(self::MOST_RELEVANT_FIRST, $relevance->sql), $relevance->bindings);
    }
}
