import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { WordmarkMark } from '@/components/shared/Wordmark';
import { Button } from '@/components/ui/button';
import { useLogOut } from '@/hooks/use-log-out';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title: string;
    children: ReactNode;
};

/**
 * The shell for the one screen a person cannot walk away from: an account exists,
 * a business does not, and every tenant route answers 403 until one does.
 *
 * It is a layout of its own rather than `AdminLayout` with the navigation turned
 * off, because the two differ in what they promise. A dashboard header offers
 * ways out; this one has none to offer — the wordmark leads nowhere, since the
 * only destination it could name would bounce straight back here.
 *
 * Logging out stays, and it is the only way out on purpose: someone who reached
 * this screen as the wrong account needs to leave it, and the button carries the
 * cache-clearing invariant with it.
 */
export function OnboardingLayout({ title, children }: Props) {
    const { name } = usePage().props;
    const { t } = useTranslation('common');
    const logOut = useLogOut();

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-5 sm:px-8">
                    <WordmarkMark name={name} />

                    <Button variant="ghost" size="sm" onClick={logOut} className="h-11 px-3">
                        {t('nav.logOut')}
                    </Button>
                </div>
            </header>

            {/*
             * The card sits at the top of the column rather than centred in it: on
             * a phone a vertically centred form jumps the moment the keyboard
             * opens, and this form is long enough to scroll anyway.
             *
             * The column is centred by margin rather than by a centred grid track:
             * an `auto` track is sized from its content's max-content width, which
             * a single long unbroken word can push past a 320px screen.
             */}
            <main className="flex-1 px-5 py-10 sm:px-8 sm:py-16">
                <div className="mx-auto w-full max-w-md">{children}</div>
            </main>
        </div>
    );
}
