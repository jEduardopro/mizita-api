import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Wordmark } from '@/components/shared/Wordmark';
import { Button } from '@/components/ui/button';
import { useLogOut } from '@/hooks/use-log-out';

type Props = {
    /** The tab title and the heading of the screen. */
    title: string;
    description?: string;
    /** Screen-level actions, rendered next to the heading. */
    actions?: ReactNode;
    children: ReactNode;
};

export function AdminLayout({ title, description, actions, children }: Props) {
    const { name } = usePage().props;
    const { t } = useTranslation('common');
    const logOut = useLogOut();

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-5 sm:px-8">
                    <Wordmark name={name} href="/dashboard" />

                    {/* `sm` sets the type, not the box: the height is grown to the
                        44px tap-target floor. */}
                    <Button variant="ghost" size="sm" onClick={logOut} className="h-11 px-3">
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
