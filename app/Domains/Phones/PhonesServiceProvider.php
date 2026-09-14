<?php

declare(strict_types=1);

namespace App\Domains\Phones;

use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Infrastructure\Eloquent\EloquentPhoneRepository;
use Illuminate\Support\ServiceProvider;

final class PhonesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PhoneRepository::class, EloquentPhoneRepository::class);
    }

    // No boot() and no routes.php on purpose: a phone is always given and read
    // through its owner, so its domain answers "whose number is this?".
}
