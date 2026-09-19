import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    availabilityRangeFor,
    dayOfInstantIn,
    monthOfIsoDate,
    todayIn,
} from '@/domains/public-catalog/components/booking/booking-slots';
import { BookingFlowFallback } from '@/domains/public-catalog/components/booking/BookingFlowFallback';
import { BookingFlowLayout } from '@/domains/public-catalog/components/booking/BookingFlowLayout';
import { BookingSlotPicker } from '@/domains/public-catalog/components/booking/BookingSlotPicker';
import { BookingTimezoneSelect } from '@/domains/public-catalog/components/booking/BookingTimezoneSelect';
import {
    useBookingFlow,
    type BookingFlow,
} from '@/domains/public-catalog/components/booking/use-booking-flow';
import { usePublicAvailability } from '@/domains/public-catalog/queries';
import type { PublicAvailabilityQuery } from '@/domains/public-catalog/types';
import { brandColorClasses } from '@/lib/booking-brand';

type Props = {
    slug: string;
};

function timezoneOf(flow: BookingFlow): string {
    return flow.status === 'ready' ? flow.timezone : '';
}

function monthOfSelection(flow: BookingFlow): string {
    const timezone = timezoneOf(flow);

    return monthOfIsoDate(dayOfInstantIn(flow.selection.at, timezone) ?? todayIn(timezone));
}

function availabilityQuery(flow: BookingFlow, month: string): PublicAvailabilityQuery | null {
    if (flow.status !== 'ready' || flow.service === null || flow.staffMember === null) {
        return null;
    }

    const range = availabilityRangeFor(month);

    if (range === null) {
        return null;
    }

    return {
        service_id: flow.service.id,
        staff_id: flow.staffMember.id,
        from: range.from,
        to: range.to,
    };
}

export default function BookingTimeStep({ slug }: Props) {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');
    const flow = useBookingFlow(slug, 'time');
    const [chosenMonth, setChosenMonth] = useState<string | null>(null);

    const month = chosenMonth ?? monthOfSelection(flow);
    const availability = usePublicAvailability(slug, availabilityQuery(flow, month));

    if (flow.status !== 'ready') {
        return <BookingFlowFallback flow={flow} />;
    }

    const { page } = flow;

    return (
        <BookingFlowLayout
            flow={flow}
            title={t('booking.flow.time.title')}
            description={t('booking.flow.time.description', { timezone: flow.timezone })}
        >
            <div className="sm:max-w-sm">
                <BookingTimezoneSelect
                    value={flow.timezone}
                    onChange={(timezone) =>
                        flow.goTo('time', { at: flow.startsAt, tz: timezone })
                    }
                />
            </div>

            {availability.isError ? (
                <div className="grid justify-items-center gap-4 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                    <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                        {t('booking.flow.errors.availability')}
                    </p>

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void availability.refetch()}
                        className="h-11 px-5"
                    >
                        {tCommon('actions.tryAgain')}
                    </Button>
                </div>
            ) : (
                <BookingSlotPicker
                    days={availability.data ?? []}
                    timezone={flow.timezone}
                    month={month}
                    lastBookableDate={page.last_bookable_date}
                    selectedStartsAt={flow.startsAt}
                    isLoading={availability.isPending || availability.isPlaceholderData}
                    accent={brandColorClasses[page.brand.accent_color]}
                    buttonShape={page.brand.button_shape}
                    onMonthChange={setChosenMonth}
                    onSelect={(startsAt) => flow.goTo('details', { at: startsAt })}
                />
            )}
        </BookingFlowLayout>
    );
}
