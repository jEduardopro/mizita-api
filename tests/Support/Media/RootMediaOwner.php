<?php

declare(strict_types=1);

namespace Tests\Support\Media;

final class RootMediaOwner
{
    public function name(): string
    {
        return 'An owner that belongs to no business.';
    }
}
