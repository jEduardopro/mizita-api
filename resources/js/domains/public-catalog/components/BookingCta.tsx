import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import {
    brandColorClasses,
    BUTTON_SHAPE_CLASSES,
    type BrandColor,
    type ButtonShape,
} from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import { WEEKDAY_IN_SENTENCE_LABEL_KEYS } from '@/lib/weekdays';
import type { PublicOpenState } from '../types';

type Props = {
    href: string;
    openState: PublicOpenState;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    className?: string;
};

const CTA_CLASS =
    'flex min-h-12 items-center justify-center gap-2 px-6 py-2.5 text-center text-base font-medium text-pretty outline-none focus-visible:ring-3 focus-visible:ring-ring/50';

function useClosedLabel(state: PublicOpenState): string | null {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    if (state.open) {
        return null;
    }

    if (state.opens_on_weekday === null || state.opens_at === null) {
        return t('booking.cta.closedIndefinitely');
    }

    return t('booking.cta.closed', {
        day: tCommon(WEEKDAY_IN_SENTENCE_LABEL_KEYS[state.opens_on_weekday]),
        time: formatTimeOfDay(state.opens_at),
    });
}

export function BookingCta({ href, openState, accentColor, buttonShape, className }: Props) {
    const { t } = useTranslation('public');

    const accent = brandColorClasses[accentColor];
    const closedLabel = useClosedLabel(openState);
    const shape = BUTTON_SHAPE_CLASSES[buttonShape];

    if (closedLabel !== null) {
        return (
            <button
                type="button"
                disabled
                className={cn(
                    CTA_CLASS,
                    'border border-border bg-muted text-sm text-muted-foreground',
                    shape,
                    className,
                )}
            >
                <span
                    aria-hidden="true"
                    className="size-1.5 shrink-0 rounded-full bg-muted-foreground"
                />

                {closedLabel}
            </button>
        );
    }

    return (
        <Link
            href={href}
            className={cn(
                CTA_CLASS,
                'motion-safe:transition-opacity hover:opacity-90',
                accent.accent,
                accent.accentForeground,
                shape,
                className,
            )}
        >
            {t('booking.cta.book')}
        </Link>
    );
}
