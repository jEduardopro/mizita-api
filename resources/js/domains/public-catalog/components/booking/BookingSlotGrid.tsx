import { cn } from 'cn';
import { CalendarX2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import type { BookingSlot } from './booking-slots';

type Props = {
    slots: BookingSlot[];
    selectedStartsAt: string | null;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onSelect(startsAt: string): void;
};

const SLOT_CLASS =
    'flex h-11 w-full items-center justify-center px-2 text-[0.9375rem] font-medium tabular-nums outline-none focus-visible:ring-3 focus-visible:ring-ring/50 motion-safe:transition-colors';

export function BookingSlotGrid({
    slots,
    selectedStartsAt,
    accent,
    buttonShape,
    onSelect,
}: Props) {
    const { t } = useTranslation('public');

    if (slots.length === 0) {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <CalendarX2 aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('booking.flow.empty.slots')}
                </p>
            </div>
        );
    }

    return (
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-2">
            {slots.map((slot) => {
                const isSelected = slot.startsAt === selectedStartsAt;

                return (
                    <li key={slot.startsAt} className="min-w-0">
                        <button
                            type="button"
                            onClick={() => onSelect(slot.startsAt)}
                            aria-current={isSelected ? true : undefined}
                            className={cn(
                                SLOT_CLASS,
                                BUTTON_SHAPE_CLASSES[buttonShape],
                                isSelected
                                    ? cn(accent.accent, accent.accentForeground)
                                    : 'border border-border hover:bg-muted',
                            )}
                        >
                            {slot.label}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
