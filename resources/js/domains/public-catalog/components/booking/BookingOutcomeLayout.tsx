import { Head, Link } from '@inertiajs/react';
import { cn } from 'cn';
import { Store } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, THEME_SCOPES } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../../types';
import { businessPageUrl } from './booking-steps';

type Props = {
    page: PublicBusinessPage;
    title: string;
    children: ReactNode;
};

export function BookingOutcomeLayout({ page, title, children }: Props) {
    const { t } = useTranslation('public');

    const accent = brandColorClasses[page.brand.accent_color];
    const themeScope = THEME_SCOPES[page.brand.theme];

    return (
        <div className={cn('flex min-h-svh flex-col bg-background text-foreground', themeScope)}>
            <Head title={title} />

            <header className="border-b border-border">
                <div className="mx-auto flex h-14 w-full max-w-3xl items-center gap-2 px-5 sm:px-8">
                    <span
                        className={cn(
                            'grid size-8 shrink-0 place-content-center overflow-hidden rounded-full',
                            accent.surface,
                        )}
                    >
                        {page.logo_url === null ? (
                            <Store aria-hidden="true" className="size-4 text-muted-foreground" />
                        ) : (
                            <img src={page.logo_url} alt="" className="size-full object-cover" />
                        )}
                    </span>

                    <span className="truncate font-heading text-sm font-medium tracking-[-0.01em]">
                        {page.name}
                    </span>
                </div>
            </header>

            <main className="flex-1">
                <div className="mx-auto grid w-full max-w-3xl gap-6 px-5 py-8 sm:px-8 sm:py-12">
                    {children}

                    <Link
                        href={businessPageUrl(page.slug)}
                        className="justify-self-start rounded-md text-sm font-medium underline underline-offset-4 outline-none hover:no-underline focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('booking.flow.confirmed.backToPage', { name: page.name })}
                    </Link>
                </div>
            </main>
        </div>
    );
}
