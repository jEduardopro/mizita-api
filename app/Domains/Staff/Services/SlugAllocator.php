<?php

declare(strict_types=1);

namespace App\Domains\Staff\Services;

use App\Domains\Staff\ValueObjects\BookingSlug;

final class SlugAllocator
{
    /**
     * @param  list<string>  $taken
     */
    public function allocate(BookingSlug $base, array $taken): BookingSlug
    {
        $candidate = $base;
        $suffix = BookingSlug::FIRST_SUFFIX;

        while (in_array($candidate->value, $taken, true)) {
            $candidate = $base->withSuffix($suffix);
            $suffix++;
        }

        return $candidate;
    }
}
