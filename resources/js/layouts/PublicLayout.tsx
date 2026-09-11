import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Wordmark } from '@/components/Wordmark';
import { Button } from '@/components/ui/button';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title?: string;
    children: ReactNode;
};

/**
 * The shell for everything an anonymous visitor can reach: the landing page and,
 * later, the public catalog and the booking funnel.
 */
export function PublicLayout({ title, children }: Props) {
    const { name, auth } = usePage().props;
    const { t } = useTranslation('common');

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-5 sm:px-8">
                    <Wordmark name={name} />

                    <nav className="flex items-center gap-1.5">
                        {auth.isAuthenticated ? (
                            <Button asChild size="sm">
                                <Link href="/dashboard">{t('nav.dashboard')}</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href="/login">{t('nav.logIn')}</Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href="/register">{t('nav.createAccount')}</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border">
                <div className="mx-auto w-full max-w-5xl px-5 py-6 text-xs text-muted-foreground sm:px-8">
                    <p>{t('footer.tagline', { name })}</p>
                </div>
            </footer>
        </div>
    );
}
