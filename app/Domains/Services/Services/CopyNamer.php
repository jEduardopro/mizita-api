<?php

declare(strict_types=1);

namespace App\Domains\Services\Services;

final class CopyNamer
{
    private const FIRST_SUFFIX = 2;

    private const SEPARATOR = ' ';

    /**
     * @param  list<string>  $taken
     */
    public function allocate(string $base, array $taken): string
    {
        $occupied = $this->occupiedSuffixes($base, $taken);

        if (! in_array(null, $occupied, true)) {
            return $base;
        }

        $suffix = self::FIRST_SUFFIX;

        while (in_array($suffix, $occupied, true)) {
            $suffix++;
        }

        return $base.self::SEPARATOR.$suffix;
    }

    /**
     * @param  list<string>  $taken
     * @return list<int|null>
     */
    private function occupiedSuffixes(string $base, array $taken): array
    {
        $pattern = '/^'.preg_quote($base, '/').'(?: (\d+))?$/iu';
        $occupied = [];

        foreach ($taken as $candidate) {
            if (preg_match($pattern, trim($candidate), $matches) !== 1) {
                continue;
            }

            $occupied[] = isset($matches[1]) ? (int) $matches[1] : null;
        }

        return $occupied;
    }
}
