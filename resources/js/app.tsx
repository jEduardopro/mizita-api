import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nextProvider } from 'react-i18next';
import { AppToaster } from '@/components/shared/AppToaster';
import { initializeAppearance } from '@/lib/appearance';
import { initI18n } from '@/lib/i18n';
import { queryClient } from '@/lib/query-client';

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
    // Inertia v3 creates the React root itself, so `withApp` is the only hook
    // that runs before the first paint could flash the wrong language.
    withApp: (app, { page }) => {
        const i18n = initI18n(page.props.locale, page.props.supportedLocales);

        initializeAppearance(page.props.appearance);

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
