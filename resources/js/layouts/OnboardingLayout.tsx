import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { WordmarkMark } from '@/components/shared/Wordmark';
import { Button } from '@/components/ui/button';
import { useLogOut } from '@/hooks/use-log-out';

type Props = {
    title: string;
    children: ReactNode;
};

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

            <main className="flex-1 px-5 py-10 sm:px-8 sm:py-16">
                <div className="mx-auto w-full max-w-md">{children}</div>
            </main>
        </div>
    );
}
