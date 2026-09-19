import { TriangleAlert } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { dateFromIso, isoFromDate } from '@/components/form/date-format';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    AppointmentCalendar,
    type AppointmentCalendarHandle,
    type CalendarViewName,
} from '@/domains/appointments/components/AppointmentCalendar';
import { AppointmentDetailsPopover } from '@/domains/appointments/components/AppointmentDetailsPopover';
import { CalendarToolbar } from '@/domains/appointments/components/CalendarToolbar';
import { DeleteAppointmentDialog } from '@/domains/appointments/components/DeleteAppointmentDialog';
import { NewAppointmentDialog } from '@/domains/appointments/components/NewAppointmentDialog';
import { useAppointments } from '@/domains/appointments/queries';
import type { Appointment, AppointmentRange } from '@/domains/appointments/types';
import { useBusinessSettings } from '@/domains/businesses/queries';
import type { ScheduleRule } from '@/domains/businesses/types';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { AdminLayout } from '@/layouts/AdminLayout';
import { todayAsIsoDate } from '@/lib/time';

const EMPTY_APPOINTMENTS: Appointment[] = [];

const EMPTY_SCHEDULE: ScheduleRule[] = [];

function browserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

function startOfWeek(date: Date): Date {
    const day = date.getDay();
    const distanceFromMonday = day === 0 ? 6 : day - 1;
    const start = new Date(date);
    start.setDate(start.getDate() - distanceFromMonday);

    return start;
}

function initialRange(view: CalendarViewName): AppointmentRange {
    const today = new Date();

    if (view === 'day') {
        const from = new Date(today);
        from.setHours(0, 0, 0, 0);
        const to = new Date(from);
        to.setDate(to.getDate() + 1);

        return { from: from.toISOString(), to: to.toISOString() };
    }

    const from = startOfWeek(today);
    from.setHours(0, 0, 0, 0);
    const to = new Date(from);
    to.setDate(to.getDate() + 7);

    return { from: from.toISOString(), to: to.toISOString() };
}

export default function Calendar() {
    const { t, i18n } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const isDesktop = useIsDesktop();
    const calendarRef = useRef<AppointmentCalendarHandle>(null);

    const [view, setView] = useState<CalendarViewName>(() => (isDesktop ? 'week' : 'day'));
    const [range, setRange] = useState<AppointmentRange>(() => initialRange(view));
    const [selectedDate, setSelectedDate] = useState<string>(() => todayAsIsoDate());

    const [createDialog, setCreateDialog] = useState<{ open: boolean; prefillStartsAt: string | null }>({
        open: false,
        prefillStartsAt: null,
    });
    const [editingAppointment, setEditingAppointment] = useState<Appointment | null>(null);
    const [detailsAppointment, setDetailsAppointment] = useState<Appointment | null>(null);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [appointmentToDelete, setAppointmentToDelete] = useState<Appointment | null>(null);

    const { data: businessSettings, isPending: isSettingsPending } = useBusinessSettings();
    const timezone = businessSettings?.timezone ?? browserTimezone();
    const schedule = businessSettings?.schedule ?? EMPTY_SCHEDULE;

    const { data: appointments, isError, refetch } = useAppointments(range);

    const handleRangeChange = useCallback((next: AppointmentRange) => setRange(next), []);

    const handleSelectedDateChange = useCallback((date: string) => setSelectedDate(date), []);

    const handleClickSlot = useCallback((startsAt: string) => {
        setCreateDialog({ open: true, prefillStartsAt: startsAt });
    }, []);

    const handleClickAppointment = useCallback((appointment: Appointment) => {
        setDetailsAppointment(appointment);
        setDetailsOpen(true);
    }, []);

    const monthYearLabel = new Intl.DateTimeFormat(i18n.language, { month: 'long', year: 'numeric' }).format(
        dateFromIso(selectedDate) ?? new Date(),
    );

    return (
        <AdminLayout title={t('calendar.title')} fullBleed>
            <div className="flex h-full min-h-0 flex-1 flex-col">
                <CalendarToolbar
                    label={monthYearLabel}
                    view={view}
                    onViewChange={(next) => {
                        setView(next);
                        calendarRef.current?.setView(next);
                    }}
                    onToday={() => calendarRef.current?.goToToday()}
                    onPrevious={() => calendarRef.current?.shift(-1)}
                    onNext={() => calendarRef.current?.shift(1)}
                    selectedDate={dateFromIso(selectedDate) ?? new Date()}
                    onSelectDate={(date) => calendarRef.current?.goToDate(isoFromDate(date))}
                    timezone={timezone}
                />

                <div className="relative min-h-0 flex-1">
                    {isSettingsPending ? (
                        <Skeleton className="h-full w-full rounded-none" />
                    ) : (
                        <AppointmentCalendar
                            ref={calendarRef}
                            className="h-full"
                            appointments={appointments ?? EMPTY_APPOINTMENTS}
                            timezone={timezone}
                            locale={i18n.language}
                            schedule={schedule}
                            initialView={view}
                            onRangeChange={handleRangeChange}
                            onSelectedDateChange={handleSelectedDateChange}
                            onClickSlot={handleClickSlot}
                            onClickAppointment={handleClickAppointment}
                        />
                    )}

                    {isError ? (
                        <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/80 px-4 backdrop-blur-sm">
                            <div className="grid max-w-sm justify-items-center gap-3 text-center">
                                <TriangleAlert className="size-6 text-muted-foreground" aria-hidden="true" />

                                <p className="text-sm font-medium">{t('calendar.errors.load')}</p>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => void refetch()}
                                    className="h-10 px-4"
                                >
                                    {tCommon('actions.tryAgain')}
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>

            <NewAppointmentDialog
                mode="create"
                open={createDialog.open}
                onOpenChange={(open) => setCreateDialog((current) => ({ ...current, open }))}
                appointment={null}
                timezone={timezone}
                prefillStartsAt={createDialog.prefillStartsAt}
            />

            <NewAppointmentDialog
                mode="edit"
                open={editingAppointment !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        setEditingAppointment(null);
                    }
                }}
                appointment={editingAppointment}
                timezone={timezone}
            />

            <AppointmentDetailsPopover
                appointment={detailsAppointment}
                open={detailsOpen}
                onOpenChange={setDetailsOpen}
                timezone={timezone}
                onEdit={(appointment) => {
                    setDetailsOpen(false);
                    setEditingAppointment(appointment);
                }}
                onDelete={(appointment) => {
                    setDetailsOpen(false);
                    setAppointmentToDelete(appointment);
                }}
            />

            {appointmentToDelete !== null ? (
                <DeleteAppointmentDialog
                    appointment={appointmentToDelete}
                    open={appointmentToDelete !== null}
                    onOpenChange={(open) => {
                        if (! open) {
                            setAppointmentToDelete(null);
                        }
                    }}
                />
            ) : null}
        </AdminLayout>
    );
}
