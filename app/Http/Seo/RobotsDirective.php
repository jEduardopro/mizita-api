<?php

declare(strict_types=1);

namespace App\Http\Seo;

enum RobotsDirective: string
{
    public const HEADER = 'X-Robots-Tag';

    case Index = 'index, follow';
    case NoIndex = 'noindex';
    case NoIndexNoFollow = 'noindex, nofollow';
}
