import { Pencil, Trash2 } from 'lucide-react';
import 'temporal-polyfill/global';
import { useTranslation } from 'react-i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatTimeOfDay } from '@/lib/time';
import type { Appointment } from '../types';

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

export function AppointmentDetailsPopover({ appointment, open, onOpenChange, timezone, onEdit, onDelete }: Props) {
    const { t, i18n } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="bottom" className="max-h-[85svh] pb-[env(safe-area-inset-bottom)]">
                <SheetHeader>
                    <SheetTitle>{t('calendar.appointment.details.title')}</SheetTitle>
                </SheetHeader>

                {appointment !== null ? (
                    <div className="grid gap-4 overflow-y-auto overscroll-contain px-4 pb-4">
                        <div className="flex items-center gap-3">
                            <ServiceColorTile
                                color={appointment.service.color}
                                imageUrl={null}
                                className="size-10 rounded-lg"
                            />

                            <div className="grid min-w-0 gap-0.5">
                                <p className="truncate font-medium">{appointment.service.name}</p>

                                <p className="text-sm text-muted-foreground">
                                    {t('calendar.appointment.details.duration', {
                                        minutes: appointment.duration_minutes,
                                    })}
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
                        </div>
                    </div>
                ) : null}

                <SheetFooter className="flex-row justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => appointment !== null && onDelete(appointment)}
                        className="h-11 px-4 text-destructive hover:text-destructive md:h-9"
                    >
                        <Trash2 aria-hidden="true" />
                        {tCommon('actions.delete')}
                    </Button>

                    <Button
                        type="button"
                        variant="brand"
                        onClick={() => appointment !== null && onEdit(appointment)}
                        className="h-11 px-4 md:h-9"
                    >
                        <Pencil aria-hidden="true" />
                        {tCommon('actions.edit')}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
