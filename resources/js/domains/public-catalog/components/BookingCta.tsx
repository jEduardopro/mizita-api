import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import {
    brandColorClasses,
    BUTTON_SHAPE_CLASSES,
    type BrandColor,
    type ButtonShape,
} from '@/lib/booking-brand';

type Props = {
    href: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    className?: string;
};

const CTA_CLASS =
    'flex min-h-12 items-center justify-center gap-2 px-6 py-2.5 text-center text-base font-medium text-pretty outline-none focus-visible:ring-3 focus-visible:ring-ring/50';

export function BookingCta({ href, accentColor, buttonShape, className }: Props) {
    const { t } = useTranslation('public');

    const accent = brandColorClasses[accentColor];
    const shape = BUTTON_SHAPE_CLASSES[buttonShape];

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
