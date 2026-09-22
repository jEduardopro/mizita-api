import 'temporal-polyfill/global';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatPhoneNumber } from '@/lib/phone';
import { formatServiceSummary } from '@/lib/service-format';
import { formatTimeOfDay } from '@/lib/time';
import { isCancelled } from './appointment-status';
import { AppointmentDetailsActions } from './AppointmentDetailsActions';
import { CancelAppointmentDialog } from './CancelAppointmentDialog';
import { CancelledAppointmentNotice } from './CancelledAppointmentNotice';
import type { Appointment, AppointmentCustomer } from '../types';

type Props = {
    appointment: Appointment | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    timezone: string;
    onEdit: (appointment: Appointment) => void;
    onDelete: (appointment: Appointment) => void;
};

function timeOfDay(instant: string, timezone: string): string {
    return Temporal.Instant.from(instant)
        .toZonedDateTimeISO(timezone)
        .toPlainTime()
        .toString({ smallestUnit: 'minute' });
}

function dayLabel(instant: string, timezone: string, locale: string): string {
    const date = new Date(Temporal.Instant.from(instant).epochMilliseconds);

    return new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: timezone,
    }).format(date);
}

type ContactLineProps = {
    customer: Pick<AppointmentCustomer, 'email' | 'phone'>;
};

function CustomerContactLine({ customer }: ContactLineProps) {
    const phone = customer.phone === null ? null : formatPhoneNumber(customer.phone);
    const details = [customer.email, phone].filter((detail): detail is string => detail !== null);

    if (details.length === 0) {
        return null;
    }

    return <p className="break-words text-muted-foreground">{details.join(' · ')}</p>;
}

export function AppointmentDetailsPopover({ appointment, open, onOpenChange, timezone, onEdit, onDelete }: Props) {
    const { t, i18n } = useTranslation('admin');
    const [appointmentToCancel, setAppointmentToCancel] = useState<Appointment | null>(null);

    const cancelled = appointment !== null && isCancelled(appointment);

    function requestCancel(target: Appointment) {
        onOpenChange(false);
        setAppointmentToCancel(target);
    }

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent side="bottom" className="max-h-[85svh] pb-[env(safe-area-inset-bottom)]">
                    <SheetHeader>
                        <SheetTitle>{t('calendar.appointment.details.title')}</SheetTitle>
                    </SheetHeader>

                    {appointment !== null ? (
                        <div className="grid gap-4 overflow-y-auto overscroll-contain px-4 pb-4">
                            {cancelled ? (
                                <CancelledAppointmentNotice
                                    cancelledAt={appointment.cancelled_at}
                                    cancelledBy={appointment.cancelled_by}
                                    timezone={timezone}
                                />
                            ) : null}

                            <div className="flex items-center gap-3">
                                <ServiceColorTile
                                    color={appointment.service.color}
                                    imageUrl={null}
                                    className="size-10 rounded-lg"
                                />

                                <div className="grid min-w-0 gap-0.5">
                                    <p className="truncate font-medium">{appointment.service.name}</p>

                                    <p className="text-sm text-muted-foreground">
                                        {formatServiceSummary(appointment.service, i18n.language, t)}
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-1 text-sm">
                                <p className="font-medium capitalize">
                                    {dayLabel(appointment.starts_at, timezone, i18n.language)}
                                </p>

                                <p className="text-muted-foreground">
                                    {formatTimeOfDay(timeOfDay(appointment.starts_at, timezone))}
                                    {' – '}
                                    {formatTimeOfDay(timeOfDay(appointment.ends_at, timezone))}
                                </p>
                            </div>

                            <div className="grid gap-3 border-t border-border pt-4 text-sm">
                                <div className="grid gap-0.5">
                                    <p className="text-muted-foreground">{t('calendar.appointment.details.customer')}</p>
                                    <p className="font-medium">{appointment.customer.name}</p>
                                    <CustomerContactLine customer={appointment.customer} />
                                </div>

                                <div className="grid gap-0.5">
                                    <p className="text-muted-foreground">{t('calendar.appointment.details.staff')}</p>
                                    <p className="font-medium">{appointment.staff_member.name}</p>
                                </div>

                                <div className="grid gap-0.5">
                                    <p className="text-muted-foreground">{t('calendar.appointment.details.notes')}</p>
                                    <p className="text-pretty">
                                        {appointment.notes ?? t('calendar.appointment.details.empty')}
                                    </p>
                                </div>

                                {appointment.reference_code !== null ? (
                                    <div className="grid gap-0.5">
                                        <p className="text-muted-foreground">
                                            {t('calendar.appointment.details.reference')}
                                        </p>
                                        <p className="font-mono text-base font-medium tracking-[0.15em]">
                                            {appointment.reference_code}
                                        </p>
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ) : null}

                    {cancelled ? (
                        <p className="px-4 text-sm text-muted-foreground">
                            {t('calendar.appointment.cancelled.locked')}
                        </p>
                    ) : null}

                    {appointment !== null ? (
                        <SheetFooter>
                            <AppointmentDetailsActions
                                appointment={appointment}
                                onEdit={() => onEdit(appointment)}
                                onCancel={() => requestCancel(appointment)}
                                onDelete={() => onDelete(appointment)}
                            />
                        </SheetFooter>
                    ) : null}
                </SheetContent>
            </Sheet>

            {appointmentToCancel !== null ? (
                <CancelAppointmentDialog
                    appointment={appointmentToCancel}
                    open
                    onOpenChange={(next) => {
                        if (! next) {
                            setAppointmentToCancel(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}
