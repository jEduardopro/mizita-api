<?php

use App\Domains\PublicCatalog\Application\Dtos\ConfirmBusinessPageInput;
use App\Domains\PublicCatalog\Application\UseCases\ConfirmBusinessPage;
use App\Domains\PublicCatalog\PublicCatalogServiceProvider;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

Route::get('/', fn () => Inertia::render('public/welcome'));

Route::get('/terms', fn () => Inertia::render('public/legal/terms'))->name('legal.terms');
Route::get('/privacy', fn () => Inertia::render('public/legal/privacy'))->name('legal.privacy');
Route::get('/cookies', fn () => Inertia::render('public/legal/cookies'))->name('legal.cookies');

Route::get('/onboarding', fn () => Inertia::render('admin/onboarding'))
    ->middleware(['auth', 'onboarding'])
    ->name('onboarding');

Route::permanentRedirect('/dashboard', '/calendar')->name('dashboard');

Route::middleware(['auth', 'onboarded', 'business'])->group(function (): void {
    Route::get('/calendar', fn () => Inertia::render('admin/calendar'))->name('calendar');
    Route::get('/services', fn () => Inertia::render('admin/services/index'))->name('services');
    Route::get('/services/new', fn () => Inertia::render('admin/services/create'))->name('services.create');
    Route::get('/services/{service}/edit', fn (string $service) => Inertia::render('admin/services/edit', ['serviceId' => $service]))->name('services.edit');
    Route::get('/customers', fn () => Inertia::render('admin/customers/index'))->name('customers');
    Route::get('/customers/new', fn () => Inertia::render('admin/customers/create'))->name('customers.create');
    Route::get('/customers/{customer}/edit', fn (string $customer) => Inertia::render('admin/customers/edit', ['customerId' => $customer]))->name('customers.edit');
    Route::get('/settings/profile', fn () => Inertia::render('admin/settings/profile'))->name('settings.profile');
    Route::get('/settings/business', fn () => Inertia::render('admin/settings/business'))->name('settings.business');
});

$bookingPageSlug = PublicCatalogServiceProvider::BOOKING_PAGE_SLUG_PATTERN;
$serviceSlug = PublicCatalogServiceProvider::SLUG_PATTERN;

Route::get('/{slug}', function (string $slug, ConfirmBusinessPage $confirmBusinessPage) {
    $page = $confirmBusinessPage->handle(new ConfirmBusinessPageInput($slug));

    abort_if($page->failed(), Response::HTTP_NOT_FOUND);

    return Inertia::render('public/businesses/show', ['slug' => $page->value()->slug]);
})->where('slug', $bookingPageSlug)->name('booking-page');

Route::get('/{slug}/{service}', function (string $slug, string $service, ConfirmBusinessPage $confirmBusinessPage) {
    $page = $confirmBusinessPage->handle(new ConfirmBusinessPageInput($slug));

    abort_if($page->failed(), Response::HTTP_NOT_FOUND);

    return Inertia::render('public/businesses/show', [
        'slug' => $page->value()->slug,
        'serviceSlug' => $service,
    ]);
})->where(['slug' => $bookingPageSlug, 'service' => $serviceSlug])->name('booking-page.service');
