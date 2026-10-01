<?php

use App\Domains\PublicCatalog\Application\Dtos\DescribeBusinessPageSharePreviewInput;
use App\Domains\PublicCatalog\Application\UseCases\DescribeBusinessPageSharePreview;
use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicServiceBookingLinkController;
use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicStaffBookingLinkController;
use App\Domains\PublicCatalog\PublicCatalogServiceProvider;
use App\Http\Middleware\RequireBusinessMembership;
use App\Http\Middleware\RequireFreshPassword;
use Illuminate\Http\Request;
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

Route::get('/password/change', fn (Request $request) => $request->user()->mustChangePassword()
    ? Inertia::render('auth/change-password')
    : redirect()->route('calendar'))
    ->middleware('auth')
    ->name(RequireFreshPassword::CHANGE_PASSWORD_ROUTE);

Route::get('/team-access/paused', fn () => Inertia::render('admin/team-access-paused'))
    ->middleware('auth')
    ->name(RequireBusinessMembership::TEAM_ACCESS_PAUSED_ROUTE);

Route::permanentRedirect('/dashboard', '/calendar')->name('dashboard');

Route::middleware(['auth', 'onboarded', 'business'])->group(function (): void {
    Route::get('/calendar', fn () => Inertia::render('admin/calendar'))->name('calendar');
    Route::get('/statistics', fn () => Inertia::render('admin/statistics'))->middleware('owner')->name('statistics');
    Route::get('/payments', fn () => Inertia::render('admin/payments/index'))->middleware('owner')->name('payments');
    Route::get('/services', fn () => Inertia::render('admin/services/index'))->name('services');
    Route::get('/services/new', fn () => Inertia::render('admin/services/create'))->name('services.create');
    Route::get('/services/{service}/edit', fn (string $service) => Inertia::render('admin/services/edit', ['serviceId' => $service]))->name('services.edit');
    Route::get('/customers', fn () => Inertia::render('admin/customers/index'))->name('customers');
    Route::get('/integrations', fn () => Inertia::render('admin/integrations/index'))->name('integrations');
    Route::get('/customers/new', fn () => Inertia::render('admin/customers/create'))->name('customers.create');
    Route::get('/customers/{customer}', fn (string $customer) => Inertia::render('admin/customers/show', ['customerId' => $customer]))->whereUuid('customer')->name('customers.show');
    Route::get('/customers/{customer}/edit', fn (string $customer) => Inertia::render('admin/customers/edit', ['customerId' => $customer]))->name('customers.edit');
    Route::get('/settings/profile', fn () => Inertia::render('admin/settings/profile'))->name('settings.profile');
    Route::get('/settings/team', fn () => Inertia::render('admin/settings/team'))->name('settings.team');
    Route::get('/settings/team/{staffMember}', fn (string $staffMember) => Inertia::render('admin/settings/team/show', ['staffMemberId' => $staffMember]))->whereUuid('staffMember')->name('settings.team.show');
    Route::get('/settings/business', fn () => Inertia::render('admin/settings/business'))->name('settings.business');
    Route::get('/settings/booking', fn () => Inertia::render('admin/settings/booking'))->name('settings.booking');
    Route::get('/settings/plan', fn () => Inertia::render('admin/settings/plan'))->middleware('owner')->name('settings.plan');
});

$bookingPageSlug = PublicCatalogServiceProvider::BOOKING_PAGE_SLUG_PATTERN;
$serviceSlug = PublicCatalogServiceProvider::SERVICE_PAGE_SLUG_PATTERN;
$referenceCode = PublicCatalogServiceProvider::REFERENCE_CODE_PATTERN;
$linkSlug = PublicCatalogServiceProvider::SLUG_PATTERN;

Route::get('/{slug}/book', fn (string $slug) => Inertia::render('public/bookings/service', ['slug' => $slug]))
    ->where('slug', $bookingPageSlug)->name('booking-flow.service');

Route::get('/{slug}/book/staff', fn (string $slug) => Inertia::render('public/bookings/staff', ['slug' => $slug]))
    ->where('slug', $bookingPageSlug)->name('booking-flow.staff');

Route::get('/{slug}/book/time', fn (string $slug) => Inertia::render('public/bookings/time', ['slug' => $slug]))
    ->where('slug', $bookingPageSlug)->name('booking-flow.time');

Route::get('/{slug}/book/details', fn (string $slug) => Inertia::render('public/bookings/details', ['slug' => $slug]))
    ->where('slug', $bookingPageSlug)->name('booking-flow.details');

Route::get('/{slug}/book/confirmed/{reference}', fn (string $slug, string $reference) => Inertia::render(
    'public/bookings/confirmed',
    ['slug' => $slug, 'reference' => $reference],
))->where(['slug' => $bookingPageSlug, 'reference' => $referenceCode])->name('booking-flow.confirmed');

Route::get('/{slug}/book/manage/{reference}', fn (string $slug, string $reference) => Inertia::render(
    'public/bookings/manage',
    ['slug' => $slug, 'reference' => $reference],
))->where(['slug' => $bookingPageSlug, 'reference' => $referenceCode])->name('booking-flow.manage');

Route::get('/{slug}', function (string $slug, DescribeBusinessPageSharePreview $describeSharePreview) {
    $preview = $describeSharePreview->handle(new DescribeBusinessPageSharePreviewInput($slug));

    abort_if($preview->failed(), Response::HTTP_NOT_FOUND);

    return Inertia::render('public/businesses/show', ['slug' => $slug])
        ->withViewData(['sharePreview' => $preview->value()]);
})->where('slug', $bookingPageSlug)->name('booking-page');

Route::get('/{slug}/equipo/{staffSlug}', [PublicStaffBookingLinkController::class, 'staff'])
    ->where(['slug' => $bookingPageSlug, 'staffSlug' => $linkSlug])
    ->name(PublicStaffBookingLinkController::STAFF_LINK_ROUTE);

Route::get('/{slug}/equipo/{staffSlug}/{serviceSlug}', [PublicStaffBookingLinkController::class, 'staffService'])
    ->where(['slug' => $bookingPageSlug, 'staffSlug' => $linkSlug, 'serviceSlug' => $linkSlug])
    ->name(PublicStaffBookingLinkController::STAFF_SERVICE_LINK_ROUTE);

Route::get('/{slug}/{serviceSlug}', [PublicServiceBookingLinkController::class, 'service'])
    ->where(['slug' => $bookingPageSlug, 'serviceSlug' => $serviceSlug])
    ->name(PublicServiceBookingLinkController::SERVICE_LINK_ROUTE);
