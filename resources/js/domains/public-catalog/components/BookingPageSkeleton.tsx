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

            <div className="mx-auto w-full max-w-5xl sm:px-8">
                <Skeleton className="h-[clamp(150px,24svh,240px)] w-full rounded-none sm:h-[clamp(160px,34svh,340px)] sm:rounded-2xl" />
            </div>

            <div className="relative z-10 mx-auto w-full max-w-5xl px-5 sm:px-8">
                <div className="-mt-4 grid gap-8 pb-16 sm:-mt-8 lg:-mt-12 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-10">
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
