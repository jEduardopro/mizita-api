<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Inertia renders pages, it does not carry data: no record and no Resource
     * ever becomes a shared prop. Everything below is page chrome - `errors`
     * comes from the parent and turns a Fortify validation redirect back into
     * form errors, and `locale` lets i18next boot in the language SetLocale
     * already resolved, with no default-language flash.
     *
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
        ];
    }
}
