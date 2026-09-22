import { CalendarClock, LoaderCircle } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTableError } from '@/components/shared/data-table/DataTableError';
import { dataTableStatus } from '@/components/shared/data-table/status';
import { Button } from '@/components/ui/button';
import { useIntersection } from '@/hooks/use-intersection';
import { buildAppointmentTimeline, todayInTimezone } from './appointment-timeline-groups';
import { AppointmentDetailsLauncher } from './AppointmentDetailsLauncher';
import { AppointmentTimeline } from './AppointmentTimeline';
import { AppointmentTimelineSkeleton } from './AppointmentTimelineSkeleton';
import { useInfiniteCustomerAppointments } from '../queries';
import type { Appointment } from '../types';

type Props = {
    customerId: string;
    timezone: string;
};

function AppointmentsEmptyState() {
    const { t } = useTranslation('admin');

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <CalendarClock aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('customers.show.appointments.empty.title')}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('customers.show.appointments.empty.body')}
            </p>
        </div>
    );
}

export function CustomerAppointmentsTimeline({ customerId, timezone }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    const [selectedAppointment, setSelectedAppointment] = useState<Appointment | null>(null);

    const history = useInfiniteCustomerAppointments(customerId);
    const { data, fetchNextPage, hasNextPage, isFetchingNextPage } = history;

    const loadMore = useCallback(() => void fetchNextPage(), [fetchNextPage]);

    const sentinelRef = useIntersection({
        enabled: hasNextPage && ! isFetchingNextPage,
        onIntersect: loadMore,
    });

    const groups = useMemo(() => {
        const appointments = data?.pages.flatMap((page) => page.data) ?? [];

        return buildAppointmentTimeline(
            appointments,
            timezone,
            todayInTimezone(timezone),
            ! hasNextPage,
        );
    }, [data, timezone, hasNextPage]);

    const status = dataTableStatus(history.isPending, history.isError);

    if (status === 'pending') {
        return <AppointmentTimelineSkeleton />;
    }

    if (status === 'error') {
        return <DataTableError onRetry={() => void history.refetch()} />;
    }

    if (groups.length === 0) {
        return <AppointmentsEmptyState />;
    }

    return (
        <section aria-label={t('customers.show.appointments.caption')} className="grid gap-4">
            <AppointmentTimeline
                groups={groups}
                timezone={timezone}
                onSelect={setSelectedAppointment}
            />

            <div ref={sentinelRef} aria-hidden="true" />

            {hasNextPage ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={isFetchingNextPage}
                    onClick={loadMore}
                    className="h-11 justify-self-center px-5 md:h-9"
                >
                    {isFetchingNextPage ? (
                        <>
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                            {tCommon('actions.loadingMore')}
                        </>
                    ) : (
                        tCommon('actions.loadMore')
                    )}
                </Button>
            ) : null}

            <AppointmentDetailsLauncher
                appointment={selectedAppointment}
                timezone={timezone}
                onClose={() => setSelectedAppointment(null)}
            />
        </section>
    );
}
