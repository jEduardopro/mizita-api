import '@inertiajs/core';

/**
 * The props every page receives, shared by the backend's Inertia middleware.
 *
 * Inertia v3 types shared props through declaration merging on `InertiaConfig`
 * rather than through a `PageProps` interface, so `usePage().props` is typed
 * everywhere without a generic argument at the call site.
 *
 * `errors` is deliberately absent: Inertia's own `Page` type already adds it as
 * `Record<string, string>`, and redeclaring it here would only risk drift.
 *
 * The shape is intentionally minimal. Inertia renders pages; it does not carry
 * data, so `auth` is a boolean rather than a serialised user — the dashboard
 * reads the user from `/api/user` like any other client will.
 */
declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            /** The application name, used for the wordmark and the tab title. */
            name: string;
            auth: {
                isAuthenticated: boolean;
            };
            /** A flash message from the session, e.g. after requesting a reset link. */
            status: string | null;
        };
    }
}
