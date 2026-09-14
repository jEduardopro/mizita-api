import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nextProvider } from 'react-i18next';
import { AppToaster } from '@/components/shared/AppToaster';
import { initI18n } from '@/lib/i18n';
import { queryClient } from '@/lib/query-client';

// The QueryClient and the toaster sit above the page so neither the cache nor a
// message raised by a navigating request is unmounted by that navigation.
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
    // Inertia v3 creates the React root itself when no `setup` is given;
    // `withApp` is the supported hook for wrapping it in providers, and it runs
    // before React renders — the only moment i18next can be initialised without
    // the first paint flashing the wrong language.
    withApp: (app, { page }) => {
        const i18n = initI18n(page.props.locale, page.props.supportedLocales);

        return (
            <I18nextProvider i18n={i18n}>
                <QueryClientProvider client={queryClient}>
                    {app}
                    <AppToaster />
                </QueryClientProvider>
            </I18nextProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
        delay: 200,
    },
});
