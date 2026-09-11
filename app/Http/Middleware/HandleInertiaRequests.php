<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Inertia renders pages, it does not carry data: no record and no Resource
     * ever becomes a shared prop. Pages read their data from /api instead, which
     * keeps a single contract for the web and the future native client.
     *
     * The three props below are page chrome, not data:
     *  - `name` titles the document.
     *  - `errors` comes from the parent and is what turns a Fortify validation
     *    redirect back into form errors.
     *  - `status` is the flash string Fortify sets for password reset feedback.
     *  - `auth.isAuthenticated` is a boolean only; the dashboard fetches the
     *    signed-in user from GET /api/user.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'status' => fn () => $request->session()->get('status'),
            'auth' => [
                'isAuthenticated' => $request->user() !== null,
            ],
        ];
    }
}
