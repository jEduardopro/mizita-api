<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

it('sends an authenticated account visiting a guest page to onboarding', function (string $guestPage) {
    $this->app->make(HttpKernel::class);
    $this->actingAs(new User);

    $response = (new RedirectIfAuthenticated)->handle(
        Request::create($guestPage, 'GET'),
        static fn (): Response => new Response('the guest page', 200),
    );

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('onboarding'));
})->with([
    'login' => '/login',
    'register' => '/register',
]);
