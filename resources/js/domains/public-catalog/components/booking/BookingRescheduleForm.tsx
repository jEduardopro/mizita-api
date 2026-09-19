import { cn } from 'cn';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useServerErrors } from '@/hooks/use-server-errors';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { isRateLimitedError } from '@/lib/http';
import { todayAsIsoDate } from '@/lib/time';
import { isoDateIn } from '@/lib/timezone';
import { raiseSuccessToast } from '@/lib/toast';
import { usePublicAvailability, useReschedulePublicBooking } from '../../queries';
import type { PublicBookingCredentials } from '../../types';
import { BookingSlotPicker } from './BookingSlotPicker';

type Props = {
    slug: string;
    credentials: PublicBookingCredentials;
    serviceId: string;
    staffMemberId: string;
    startsAt: string;
    timezone: string;
    lastBookableDate: string;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onClose(): void;
};

const MONTH_LENGTH = 'YYYY-MM'.length;

const FIRST_DAY_OF_MONTH = '01';

function monthOf(instant: string, timezone: string): string {
    const isoDate = isoDateIn(timezone, new Date(instant)) ?? todayAsIsoDate();

    return isoDate.slice(0, MONTH_LENGTH);
}

function lastDayOf(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);

    return String(new Date(Date.UTC(year, monthNumber, 0)).getUTCDate());
}

export function BookingRescheduleForm({
    slug,
    credentials,
    serviceId,
    staffMemberId,
    startsAt,
    timezone,
    lastBookableDate,
    accent,
    buttonShape,
    onClose,
}: Props) {
    const { t } = useTranslation('public');
    const [month, setMonth] = useState(() => monthOf(startsAt, timezone));
    const [selectedStartsAt, setSelectedStartsAt] = useState<string | null>(null);

    const availability = usePublicAvailability(slug, {
        service_id: serviceId,
        staff_id: staffMemberId,
        from: `${month}-${FIRST_DAY_OF_MONTH}`,
        to: `${month}-${lastDayOf(month)}`,
    });

    const rescheduleBooking = useReschedulePublicBooking(slug, credentials);
    const serverErrors = useServerErrors();

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (selectedStartsAt === null) {
            return;
        }

        try {
            await rescheduleBooking.mutateAsync({ starts_at: selectedStartsAt });
            raiseSuccessToast(t('booking.manage.reschedule.success'));
            onClose();
        } catch (error) {
            serverErrors.capture(
                error,
                isRateLimitedError(error)
                    ? t('booking.manage.errors.tooMany')
                    : t('booking.manage.errors.reschedule'),
            );
        }
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            className="grid gap-4 border-t border-dashed border-border pt-5"
        >
            <h2 className="font-heading text-base font-semibold tracking-[-0.01em]">
                {t('booking.manage.reschedule.title')}
            </h2>

            {availability.isError ? (
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {t('booking.flow.errors.availability')}
                </p>
            ) : (
                <BookingSlotPicker
                    days={availability.data ?? []}
                    timezone={timezone}
                    month={month}
                    lastBookableDate={lastBookableDate}
                    selectedStartsAt={selectedStartsAt}
                    isLoading={availability.isPending}
                    accent={accent}
                    buttonShape={buttonShape}
                    onMonthChange={setMonth}
                    onSelect={setSelectedStartsAt}
                />
            )}

            <div className="grid gap-2 sm:flex sm:flex-wrap sm:justify-end">
                <Button type="button" variant="ghost" onClick={onClose} className="h-12 px-5">
                    {t('booking.manage.reschedule.keep')}
                </Button>

                <button
                    type="submit"
                    disabled={selectedStartsAt === null || rescheduleBooking.isPending}
                    className={cn(
                        'flex h-12 items-center justify-center px-6 text-base font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 motion-safe:transition-opacity hover:opacity-90',
                        accent.accent,
                        accent.accentForeground,
                        BUTTON_SHAPE_CLASSES[buttonShape],
                    )}
                >
                    {t('booking.manage.reschedule.submit')}
                </button>
            </div>
        </form>
    );
}
