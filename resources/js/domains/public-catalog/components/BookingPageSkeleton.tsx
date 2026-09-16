import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';

const SERVICE_ROWS = [0, 1, 2, 3];

export function BookingPageSkeleton() {
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

            <Skeleton className="aspect-[3/2] w-full rounded-none sm:aspect-[5/2] lg:aspect-[21/8]" />

            <div className="mx-auto w-full max-w-5xl px-5 sm:px-8">
                <Skeleton className="-mt-10 size-20 rounded-full sm:-mt-12 sm:size-24" />

                <Skeleton className="mt-4 h-9 w-64 max-w-full rounded-lg" />

                <Skeleton className="mt-4 h-7 w-44 rounded-full" />

                <div className="mt-8 grid gap-10 pb-16 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-12">
                    <Skeleton className="h-72 rounded-2xl lg:col-start-2 lg:row-start-1" />

                    <div className="grid gap-4 lg:col-start-1 lg:row-start-1">
                        <Skeleton className="h-7 w-40 rounded-lg" />

                        {SERVICE_ROWS.map((row) => (
                            <Skeleton key={row} className="h-16 rounded-xl" />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
