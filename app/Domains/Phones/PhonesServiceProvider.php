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

    /*
     * No boot() and no routes.php on purpose: a phone number is always given and
     * read as part of its owner, which is what keeps "whose number is this?" an
     * authorization question the owner's domain already answers.
     */
}
