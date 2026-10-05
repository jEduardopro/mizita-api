import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { PublicLayout } from '@/layouts/PublicLayout';

const HEADING_ID = 'not-found-heading';

export default function NotFound() {
    const { t } = useTranslation('public');

    return (
        <PublicLayout title={t('notFound.title')}>
            <section
                aria-labelledby={HEADING_ID}
                className="mx-auto w-full max-w-5xl px-5 py-20 sm:px-8 sm:py-28"
            >
                <p
                    aria-hidden="true"
                    className="font-heading text-[clamp(5.5rem,24vw,11rem)] leading-[0.85] font-medium tracking-[-0.06em] text-primary tabular-nums motion-safe:animate-in motion-safe:fade-in motion-safe:duration-500"
                >
                    {t('notFound.code')}
                </p>

                <h1
                    id={HEADING_ID}
                    className="mt-8 max-w-xl font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance"
                >
                    {t('notFound.heading')}
                </h1>

                <p className="mt-4 max-w-md text-base leading-relaxed text-pretty text-muted-foreground">
                    {t('notFound.body')}
                </p>

                <Button asChild variant="brand" size="xl" className="mt-8">
                    <Link href="/">{t('notFound.action')}</Link>
                </Button>
            </section>
        </PublicLayout>
    );
}
