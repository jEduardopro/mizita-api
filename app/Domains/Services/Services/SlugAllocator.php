<?php

declare(strict_types=1);

namespace App\Domains\Services\Services;

use App\Domains\Services\ValueObjects\Slug;

final class SlugAllocator
{
    /**
     * @param  list<string>  $taken
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
