import { cn } from 'cn';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { brandColorClasses, BUTTON_SHAPE_CLASSES } from '@/lib/booking-brand';
import type { PublicBooking, PublicBookingCredentials, PublicBusinessPage } from '../../types';
import { BookingCancelDialog } from './BookingCancelDialog';
import { BookingRescheduleForm } from './BookingRescheduleForm';
import { cancellationWindowNote } from './cancellation-window';

type Props = {
    page: PublicBusinessPage;
    booking: PublicBooking;
    credentials: PublicBookingCredentials;
};

type NamedRecord = {
    id: string;
    name: string;
};

const MILLISECONDS_PER_MINUTE = 60_000;

function uniqueIdByName(records: NamedRecord[], name: string): string | null {
    const matches = records.filter((record) => record.name === name);

    return matches.length === 1 ? matches[0].id : null;
}

function minutesUntil(instant: string, now: number): number {
    return (new Date(instant).getTime() - now) / MILLISECONDS_PER_MINUTE;
}

function isWithinChangeWindow(booking: PublicBooking, now: number): boolean {
    if (booking.cancellation_window_minutes === null) {
        return true;
    }

    return minutesUntil(booking.starts_at, now) >= booking.cancellation_window_minutes;
}

function isOpenToChanges(booking: PublicBooking, now: number): boolean {
    return booking.status === 'booked' && booking.changeable && isWithinChangeWindow(booking, now);
}

export function BookingManageActions({ page, booking, credentials }: Props) {
    const { t } = useTranslation('public');
    const [isRescheduling, setIsRescheduling] = useState(false);
    const [isCancelOpen, setIsCancelOpen] = useState(false);

    const serviceId = uniqueIdByName(page.services, booking.service_name);
    const staffMemberId = uniqueIdByName(page.team, booking.staff_member_name);

    const canCancel = isOpenToChanges(booking, Date.now());
    const canReschedule = canCancel && serviceId !== null && staffMemberId !== null;

    const accent = brandColorClasses[page.brand.accent_color];

    const windowNote =
        booking.cancellation_window_minutes === null
            ? null
            : cancellationWindowNote(booking.cancellation_window_minutes, t);

    const note = canReschedule ? windowNote : t('booking.manage.locked');

    if (isRescheduling && serviceId !== null && staffMemberId !== null) {
        return (
            <BookingRescheduleForm
                slug={page.slug}
                credentials={credentials}
                serviceId={serviceId}
                staffMemberId={staffMemberId}
                startsAt={booking.starts_at}
                timezone={page.timezone}
                lastBookableDate={page.last_bookable_date}
                accent={accent}
                buttonShape={page.brand.button_shape}
                onClose={() => setIsRescheduling(false)}
            />
        );
    }

    return (
        <div className="grid gap-3 border-t border-dashed border-border pt-5">
            <div className="grid gap-2 sm:flex sm:flex-wrap sm:items-center">
                <button
                    type="button"
                    disabled={! canReschedule}
                    onClick={() => setIsRescheduling(true)}
                    className={cn(
                        'flex h-12 items-center justify-center px-6 text-base font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 motion-safe:transition-opacity hover:opacity-90',
                        accent.accent,
                        accent.accentForeground,
                        BUTTON_SHAPE_CLASSES[page.brand.button_shape],
                    )}
                >
                    {t('booking.manage.reschedule.action')}
                </button>

                <Button
                    type="button"
                    variant="destructive"
                    disabled={! canCancel}
                    onClick={() => setIsCancelOpen(true)}
                    className="h-12 px-5 text-base"
                >
                    {t('booking.manage.cancel.action')}
                </Button>
            </div>

            {note === null ? null : (
                <p className="text-xs leading-relaxed text-muted-foreground">{note}</p>
            )}

            <BookingCancelDialog
                slug={page.slug}
                credentials={credentials}
                open={isCancelOpen}
                onOpenChange={setIsCancelOpen}
            />
        </div>
    );
}
