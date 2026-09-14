import '@inertiajs/core';

/**
 * Inertia v3 types shared props through declaration merging on `InertiaConfig`
 * rather than a `PageProps` interface, so `usePage().props` is typed everywhere
 * without a generic argument at the call site.
 *
 * `errors` is deliberately absent: Inertia's own `Page` type already adds it as
 * `Record<string, string>`, and redeclaring it here would only risk drift.
 */
declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            name: string;
            /** A flash message from the session, e.g. after requesting a reset link. */
            status: string | null;
            /**
             * The locale the backend resolved, through `?lang=` → `X-Locale` →
             * the `locale` cookie → `es`. It is the authority the client
             * initialises i18next with, so page copy and server-rendered
             * messages never disagree.
             */
            locale: string;
            supportedLocales: string[];
        };
    }
}
