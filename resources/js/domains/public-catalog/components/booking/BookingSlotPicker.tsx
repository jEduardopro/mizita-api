import { cn } from 'cn';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { dateFromIso } from '@/components/form/date-format';
import { Skeleton } from '@/components/ui/skeleton';
import type { BrandColorClasses, ButtonShape } from '@/lib/booking-brand';
import type { PublicAvailableDay } from '../../types';
import {
    bookableDates,
    bookingSlotDays,
    dayOfInstantIn,
    resolveSelectedDate,
    slotsOn,
    todayIn,
} from './booking-slots';
import { BookingCalendar } from './BookingCalendar';
import { BookingSlotGrid } from './BookingSlotGrid';

type Props = {
    days: PublicAvailableDay[];
    timezone: string;
    month: string;
    lastBookableDate: string;
    selectedStartsAt: string | null;
    isLoading: boolean;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onMonthChange(month: string): void;
    onSelect(startsAt: string): void;
};

const DAY_HEADING_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
};

const SKELETON_SLOTS = [0, 1, 2, 3, 4, 5, 6, 7];

const SLOT_COLUMNS_CLASS = 'grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-2';

export function BookingSlotPicker({
    days,
    timezone,
    month,
    lastBookableDate,
    selectedStartsAt,
    isLoading,
    accent,
    buttonShape,
    onMonthChange,
    onSelect,
}: Props) {
    const { i18n } = useTranslation();
    const [pickedDate, setPickedDate] = useState<string | null>(null);

    const slotDays = useMemo(
        () => bookingSlotDays(days, timezone, month),
        [days, month, timezone],
    );

    const availableDates = useMemo(() => bookableDates(slotDays), [slotDays]);

    const dayFormatter = useMemo(
        () => new Intl.DateTimeFormat(i18n.language, DAY_HEADING_FORMAT),
        [i18n.language],
    );

    const selectedDate = resolveSelectedDate(
        slotDays,
        pickedDate ?? dayOfInstantIn(selectedStartsAt, timezone),
    );

    const selectedDay = selectedDate === null ? null : dateFromIso(selectedDate);
    const hasNoSlotsYet = slotDays.length === 0;

    return (
        <div
            aria-busy={isLoading}
            className={cn(
                'grid gap-4 motion-safe:transition-opacity md:grid-cols-[auto_minmax(0,1fr)] md:items-start md:gap-6',
                isLoading && 'opacity-60',
            )}
        >
            <div className="grid min-w-0 justify-center rounded-2xl border border-border bg-card text-card-foreground">
                <BookingCalendar
                    month={month}
                    selectedDate={selectedDate}
                    availableDates={availableDates}
                    earliestDate={todayIn(timezone)}
                    latestDate={lastBookableDate}
                    accent={accent}
                    onMonthChange={onMonthChange}
                    onSelectDate={setPickedDate}
                />
            </div>

            <div className="grid min-w-0 gap-3">
                {selectedDay === null ? null : (
                    <h2 className="text-[0.9375rem] leading-snug font-medium first-letter:uppercase">
                        {dayFormatter.format(selectedDay)}
                    </h2>
                )}

                {isLoading && hasNoSlotsYet ? (
                    <div aria-hidden="true" className={SLOT_COLUMNS_CLASS}>
                        {SKELETON_SLOTS.map((slot) => (
                            <Skeleton key={slot} className="h-11 rounded-lg" />
                        ))}
                    </div>
                ) : (
                    <BookingSlotGrid
                        slots={slotsOn(slotDays, selectedDate)}
                        selectedStartsAt={selectedStartsAt}
                        accent={accent}
                        buttonShape={buttonShape}
                        onSelect={onSelect}
                    />
                )}
            </div>
        </div>
    );
}
