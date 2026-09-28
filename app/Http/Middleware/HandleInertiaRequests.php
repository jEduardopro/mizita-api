<?php

namespace App\Http\Middleware;

use App\Http\Preferences\CookiePreferences;
use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessPlan;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var array{roles: list<string>, permissions: list<string>}
     */
    private const NOTHING_GRANTED = ['roles' => [], 'permissions' => []];

    /** @var string */
    protected $rootView = 'app';

    public function __construct(
        private readonly CookiePreferences $preferences,
        private readonly BusinessAuthorization $authorization,
        private readonly BusinessPlan $plans,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'status' => fn () => $request->session()->get('status'),
            'locale' => app()->getLocale(),
            'supportedLocales' => config('localization.supported'),
            'appearance' => $this->preferences->appearance($request)->value,
            'sidebarOpen' => $this->preferences->sidebarOpen($request),
            'flash' => fn () => ['error' => $request->session()->get('error')],
            'auth' => fn (): array => $this->grantsFor($request),
            'plan' => fn (): ?array => $this->planOfCurrentBusiness(),
        ];
    }

    /**
     * @return array{name: 'free'|'complete', ends_at: ?string, entitlements: array{team: bool, max_active_services: ?int, booking_rules: bool, calendar_sync: bool}}|null
     */
    private function planOfCurrentBusiness(): ?array
    {
        if (! app()->bound(BusinessContext::class)) {
            return null;
        }

        return $this->plans->describe(app(BusinessContext::class)->currentBusinessId());
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function grantsFor(Request $request): array
    {
        $accountId = $request->user()?->uuid;

        if ($accountId === null || ! app()->bound(BusinessContext::class)) {
            return self::NOTHING_GRANTED;
        }

        return $this->authorization->grantsFor(
            (string) $accountId,
            app(BusinessContext::class)->currentBusinessId(),
        );
    }
}
