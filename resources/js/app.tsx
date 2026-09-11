import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nextProvider } from 'react-i18next';
import { initI18n } from '@/lib/i18n';
import { queryClient } from '@/lib/query-client';

/**
 * Boots the Inertia app.
 *
 * Laravel owns the routing: a controller answers a URL with
 * `Inertia::render('admin/customers/index')` and Inertia resolves that string
 * against `pages/`, so the page name is the file path verbatim.
 *
 * Data does not travel through Inertia props. A page mounts and its query hooks
 * fetch from `/api`, the same endpoints a native client will call later, which
 * is why the QueryClient is provided here — once, above the page — so the cache
 * survives every page transition.
 */
const fallbackAppName = 'Mizita';

void createInertiaApp({
    resolve: (name) =>
        resolvePageComponent<ResolvedComponent>(
            `./pages/${name}.tsx`,
            import.meta.glob<ResolvedComponent>('./pages/**/*.tsx'),
        ),
    title: (title, page) => {
        const appName = (page.props.name as string | undefined) ?? fallbackAppName;

        return title ? `${title} · ${appName}` : appName;
    },
    // Inertia v3 creates (or hydrates) the React root itself when no `setup` is
    // given; `withApp` is the supported hook for wrapping it in providers. It
    // runs once, with the initial page, before React renders anything — which is
    // the only moment i18next can be initialised without the first paint
    // flashing the wrong language.
    withApp: (app, { page }) => {
        const i18n = initI18n(page.props.locale, page.props.supportedLocales);

        return (
            <I18nextProvider i18n={i18n}>
                <QueryClientProvider client={queryClient}>{app}</QueryClientProvider>
            </I18nextProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
        delay: 200,
    },
});
