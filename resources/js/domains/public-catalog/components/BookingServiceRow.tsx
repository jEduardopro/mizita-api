import { useTranslation } from 'react-i18next';
import { AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import type { BrandColor, ButtonShape } from '@/lib/booking-brand';
import { formatMoney, isFreeAmount } from '@/lib/money';
import type { PublicService } from '../types';
import { BookingCta } from './BookingCta';

type Props = {
    service: PublicService;
    currencyCode: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
};

export function BookingServiceRow({ service, currencyCode, accentColor, buttonShape }: Props) {
    const { t, i18n } = useTranslation('public');

    const duration = t('booking.services.duration', { count: service.duration_minutes });
    const price = isFreeAmount(service.price)
        ? t('booking.services.free')
        : formatMoney(service.price, currencyCode, i18n.language);

    return (
        <AccordionItem value={service.slug} className="border-b border-border last:border-b-0">
            <AccordionTrigger className="gap-4 py-4 hover:no-underline">
                <span className="grid gap-1">
                    <span className="text-[0.9375rem] font-medium">{service.name}</span>

                    <span className="text-sm font-normal text-muted-foreground">
                        {t('booking.services.summary', { duration, price })}
                    </span>
                </span>
            </AccordionTrigger>

            <AccordionContent className="h-auto pb-5">
                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_13rem] sm:items-start">
                    <div className="grid gap-4">
                        {service.description === null ? null : (
                            <p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                                {service.description}
                            </p>
                        )}

                        <BookingCta
                            accentColor={accentColor}
                            buttonShape={buttonShape}
                            className="sm:max-w-xs"
                        />
                    </div>

                    {service.image_url === null ? null : (
                        <img
                            src={service.image_url}
                            alt=""
                            className="order-first aspect-[4/3] w-full rounded-xl object-cover sm:order-last"
                        />
                    )}
                </div>
            </AccordionContent>
        </AccordionItem>
    );
}
