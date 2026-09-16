import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import {
    brandColorClasses,
    BUTTON_SHAPE_CLASSES,
    type BrandColor,
    type ButtonShape,
} from '@/lib/booking-brand';

type Props = {
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    className?: string;
};

export function BookingCta({ accentColor, buttonShape, className }: Props) {
    const { t } = useTranslation('public');
    const noteId = useId();

    const accent = brandColorClasses[accentColor];

    return (
        <div className={cn('grid gap-2', className)}>
            <button
                type="button"
                aria-disabled="true"
                aria-describedby={noteId}
                className={cn(
                    'flex h-12 w-full cursor-default items-center justify-center px-6 text-base font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                    accent.accent,
                    accent.accentForeground,
                    BUTTON_SHAPE_CLASSES[buttonShape],
                )}
            >
                {t('booking.cta.book')}
            </button>

            <p id={noteId} className="text-center text-xs text-muted-foreground text-balance">
                {t('booking.cta.soon')}
            </p>
        </div>
    );
}
