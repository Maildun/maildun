<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Case-insensitive "contains" matching where % and _ in the search term are
 * matched literally instead of as LIKE wildcards.
 *
 * The escape character is spelled out in every LIKE because SQLite has no
 * default one, and '!' avoids the backslash quoting rules that differ between
 * SQLite and Postgres.
 */
trait SearchesText
{
    /**
     * Matches rows where any of the columns contains the term.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<literal-string>  $columns
     * @return Builder<TModel>
     */
    protected function whereAnyColumnContains(Builder $query, array $columns, string $term): Builder
    {
        $pattern = $this->containsPattern($term);

        return $query->where(function (Builder $matches) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $matches->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }

    /**
     * Matches people (contacts or subscribers) by email, either name, or the
     * full "first last" name.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function wherePersonContains(Builder $query, string $term): Builder
    {
        return $this->whereAnyColumnContains(
            $query,
            ['email', 'first_name', 'last_name', "first_name || ' ' || last_name"],
            $term,
        );
    }

    /**
     * The lowercased LIKE pattern for a term, for use with "LIKE ? ESCAPE '!'".
     */
    protected function containsPattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
    }
}
