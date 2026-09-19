import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';

const BODY_ROWS = [0, 1, 2, 3];

export function BookingFlowSkeleton() {
    const { t } = useTranslation('public');

    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={t('booking.loading')}
            className="flex min-h-svh flex-col bg-background text-foreground"
        >
            <Head title={t('booking.pageTitle')} />

            <div className="h-14 border-b border-border" />

            <div className="mx-auto grid w-full max-w-5xl gap-8 px-5 py-8 sm:px-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-10 lg:py-12">
                <Skeleton className="hidden h-64 rounded-2xl lg:col-start-2 lg:row-start-1 lg:block" />

                <div className="grid gap-6 lg:col-start-1 lg:row-start-1">
                    <Skeleton className="h-9 w-56 rounded-lg" />

                    <div className="grid gap-3">
                        {BODY_ROWS.map((row) => (
                            <Skeleton key={row} className="h-16 rounded-xl" />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
