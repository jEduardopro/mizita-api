<?php

declare(strict_types=1);

return [

    'indexable' => (bool) env('SEO_INDEXABLE', false),

    'sitemap' => [
        'cache_ttl_seconds' => 3600,
    ],

];
