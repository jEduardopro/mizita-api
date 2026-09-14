<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Services;

use App\Domains\Businesses\ValueObjects\Slug;

/**
 * A domain service rather than a method on Slug: the rule is about a slug's
 * relationship to every other slug, which no single value object can know.
 */
final class SlugAllocator
{
    /**
     * Only the base itself and base-<digits> count as collisions, so
     * "barberia-lopez" and "barberia-lopez-madrid" never compete: letting the
     * second consume suffix 2 would make the numbering lie about how many
     * businesses share a name.
     *
     * The loop terminates structurally: every occupied number comes from the
     * finite list handed in.
     *
     * @param  list<string>  $taken  slugs equal to the base or shaped base-<n>
     */
    public function allocate(Slug $base, array $taken): Slug
    {
        $occupied = $this->occupiedSuffixes($base, $taken);

        if (! in_array(null, $occupied, true)) {
            return $base;
        }

        $suffix = Slug::FIRST_SUFFIX;

        while (in_array($suffix, $occupied, true)) {
            $suffix++;
        }

        return $base->withSuffix($suffix);
    }

    /**
     * Null for the bare base, the number for a numbered variant.
     *
     * @param  list<string>  $taken
     * @return list<int|null>
     */
    private function occupiedSuffixes(Slug $base, array $taken): array
    {
        $pattern = '/^'.preg_quote($base->value, '/').'(?:-(\d+))?$/';
        $occupied = [];

        foreach ($taken as $candidate) {
            if (preg_match($pattern, $candidate, $matches) !== 1) {
                continue;
            }

            $occupied[] = isset($matches[1]) ? (int) $matches[1] : null;
        }

        return $occupied;
    }
}
