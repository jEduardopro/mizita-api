import { cn } from 'cn';
import { ChevronRight } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { formatMoney, isFreeAmount } from '@/lib/money';
import type { PublicService } from '../../types';
import { BookingServiceDetailsDialog } from './BookingServiceDetailsDialog';

type Props = {
    service: PublicService;
    currencyCode: string;
    isSelected: boolean;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    themeScope: string | undefined;
    onSelect(serviceId: string): void;
};

export function BookingServiceOption({
    service,
    currencyCode,
    isSelected,
    accent,
    buttonShape,
    themeScope,
    onSelect,
}: Props) {
    const { t } = useTranslation('public');
    const nameId = useId();
    const summaryId = useId();

    const duration = t('booking.services.duration', { count: service.duration_minutes });
    const price = isFreeAmount(service.price)
        ? t('booking.services.free')
        : formatMoney(service.price, currencyCode);
    const summary = t('booking.services.summary', { duration, price });

    return (
        <li
            className={cn(
                'relative flex min-h-16 items-center gap-4 border border-border px-5 py-3.5 motion-safe:transition-colors has-[[data-service-select]:focus-visible]:ring-3 has-[[data-service-select]:focus-visible]:ring-ring/50',
                BUTTON_SHAPE_CLASSES[buttonShape],
                isSelected ? accent.surface : 'hover:bg-muted/60',
            )}
        >
            {service.image_url === null ? null : (
                <img
                    src={service.image_url}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="size-12 shrink-0 rounded-md object-cover"
                />
            )}

            <div className="grid min-w-0 flex-1 gap-1">
                <button
                    type="button"
                    data-service-select
                    onClick={() => onSelect(service.id)}
                    aria-current={isSelected ? true : undefined}
                    aria-describedby={summaryId}
                    className="text-left text-[0.9375rem] leading-snug font-medium text-pretty outline-none after:absolute after:inset-0"
                >
                    <span id={nameId}>{service.name}</span>
                </button>

                <p className="text-sm text-pretty text-muted-foreground">
                    <span id={summaryId}>{summary}</span>{' '}
                    <span className="whitespace-nowrap">
                        <span aria-hidden="true">·</span>{' '}
                        <BookingServiceDetailsDialog
                            name={service.name}
                            imageUrl={service.image_url}
                            duration={duration}
                            price={price}
                            bufferMinutes={service.buffer_minutes}
                            description={service.description}
                            accent={accent}
                            buttonShape={buttonShape}
                            themeScope={themeScope}
                            onChoose={() => onSelect(service.id)}
                        >
                            <button
                                type="button"
                                aria-describedby={nameId}
                                className="relative z-10 rounded-sm underline underline-offset-4 outline-none after:absolute after:-inset-x-2 after:-inset-y-3 hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                {t('booking.services.details')}
                            </button>
                        </BookingServiceDetailsDialog>
                    </span>
                </p>
            </div>

            <ChevronRight
                aria-hidden="true"
                className={cn(
                    'size-5 shrink-0',
                    isSelected ? 'text-foreground' : 'text-muted-foreground',
                )}
            />
        </li>
    );
}
