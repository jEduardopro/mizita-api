import { Head, router, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Wordmark } from '@/components/Wordmark';
import { Button } from '@/components/ui/button';

type Props = {
    /** The tab title and the heading of the screen. */
    title: string;
    /** One line of context under the heading. */
    description?: string;
    /** Screen-level actions, rendered next to the heading. */
    actions?: ReactNode;
    children: ReactNode;
};

/**
 * The shell for the authenticated business dashboard.
 */
export function AdminLayout({ title, description, actions, children }: Props) {
    const { name } = usePage().props;
    const { t } = useTranslation('common');
    const queryClient = useQueryClient();

    function logOut() {
        router.post(
            '/logout',
            {},
            {
                // Query keys carry no tenant discriminator, because the backend
                // never serialises `business_id`. Dropping the cache on the way
                // out is what stops the next session reading these rows.
                //
                // `onBefore` runs before the request, so the outgoing session's
                // rows are gone the moment logout is asked for, and the same
                // ordering holds everywhere the session changes. Returning
                // `false` here would cancel the visit, hence the block body.
                onBefore: () => {
                    queryClient.clear();
                },
            },
        );
    }

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-5 sm:px-8">
                    <Wordmark name={name} href="/dashboard" />

                    <Button variant="ghost" size="sm" onClick={logOut}>
                        {t('nav.logOut')}
                    </Button>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-5 py-10 sm:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-heading text-2xl font-medium tracking-[-0.03em]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                        ) : null}
                    </div>

                    {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
                </div>

                <div className="mt-8">{children}</div>
            </main>
        </div>
    );
}
