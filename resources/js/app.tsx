import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { QueryClientProvider } from '@tanstack/react-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nextProvider } from 'react-i18next';
import { AppToaster } from '@/components/shared/AppToaster';
import { initializeAppearance } from '@/lib/appearance';
import { i18n, initI18n } from '@/lib/i18n';
import { queryClient } from '@/lib/query-client';

const fallbackAppName = 'Mizita';

function resolvePage(name: string): Promise<ResolvedComponent> {
    return resolvePageComponent<ResolvedComponent>(
        `./pages/${name}.tsx`,
        import.meta.glob<ResolvedComponent>('./pages/**/*.tsx'),
    );
}

void createInertiaApp({
    // Translations load per locale on demand, so the first render waits for them here,
    // in parallel with the page chunk; mounting earlier would paint raw translation keys.
    resolve: async (name, page) => {
        const [component] = await Promise.all([
            resolvePage(name),
            initI18n(page?.props.locale, page?.props.supportedLocales),
        ]);

        return component;
    },
    title: (title, page) => {
        const appName = (page.props.name as string | undefined) ?? fallbackAppName;

        return title ? `${title} · ${appName}` : appName;
    },
    withApp: (app, { page }) => {
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
