import { useTranslation } from 'react-i18next';
import { ComboboxField, type ComboboxOption } from '@/components/form/ComboboxField';
import { isUsableTimezone } from './booking-slots';

type Props = {
    value: string;
    onChange(timezone: string): void;
};

const TIMEZONE_OPTIONS: ComboboxOption[] = Intl.supportedValuesOf('timeZone')
    .filter(isUsableTimezone)
    .map((timezone) => ({ value: timezone, label: timezone }));

export function BookingTimezoneSelect({ value, onChange }: Props) {
    const { t } = useTranslation('public');

    return (
        <ComboboxField
            id="booking-timezone"
            label={t('booking.flow.time.timezone.label')}
            options={TIMEZONE_OPTIONS}
            value={value}
            onChange={(timezone) => onChange(timezone ?? value)}
            optionsStatus="ready"
            messages={{
                empty: t('booking.flow.time.timezone.empty'),
                results: t('booking.flow.time.timezone.results', {
                    count: TIMEZONE_OPTIONS.length,
                }),
            }}
        />
    );
}
