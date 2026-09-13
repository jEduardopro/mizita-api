<?php

declare(strict_types=1);

namespace App\Domains\Phones;

use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Infrastructure\Eloquent\EloquentPhoneRepository;
use Illuminate\Support\ServiceProvider;

final class PhonesServiceProvider extends ServiceProvider
{
    /**
     * Wire this domain's ports to their infrastructure adapters.
     */
    public function register(): void
    {
        $this->app->bind(PhoneRepository::class, EloquentPhoneRepository::class);
    }

    /*
     * There is no boot() and no routes.php on purpose: Phones exposes no HTTP
     * surface. A phone number is always given and read as part of its owner -
     * a business, a staff member, a customer - so it is reached through that
     * owner's endpoints, which is also what keeps "whose number is this?" an
     * authorization question their domain already answers. Nothing is missing
     * here; please do not add a route file.
     */
}
