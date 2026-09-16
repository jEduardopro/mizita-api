<?php

declare(strict_types=1);

namespace App\Domains\Links;

use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Infrastructure\Eloquent\EloquentLinkRepository;
use Illuminate\Support\ServiceProvider;

final class LinksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LinkRepository::class, EloquentLinkRepository::class);
    }

    // No boot() and no routes.php on purpose: a link is always given and read
    // through its owner, the same way a phone is.
}
