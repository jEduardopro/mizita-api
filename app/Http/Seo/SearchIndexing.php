<?php

declare(strict_types=1);

namespace App\Http\Seo;

final class SearchIndexing
{
    public function permitted(): bool
    {
        return (bool) config('seo.indexable');
    }

    public function directiveForIndexablePage(): RobotsDirective
    {
        return $this->permitted() ? RobotsDirective::Index : RobotsDirective::NoIndex;
    }
}
