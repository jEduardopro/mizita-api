<?php

namespace App\Http\Middleware;

use App\Http\Preferences\CookiePreferences;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    public function __construct(private readonly CookiePreferences $preferences) {}

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
            'flash' => fn () => ['error' => $request->session()->get('error')],
        ];
    }
}
