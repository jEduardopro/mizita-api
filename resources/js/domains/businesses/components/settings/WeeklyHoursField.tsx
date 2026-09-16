import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import {
    WEEKDAYS,
    type TimeInterval,
    type WeekdayNumber,
    type WeeklyHours,
} from '@/lib/booking-brand';
import { WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';
import { DayIntervalsRow } from './DayIntervalsRow';

const FIELD_ID = 'weekly-hours';

const OPENING_INTERVAL: TimeInterval = { starts_at: '09:00', ends_at: '17:00' };

const BLANK_INTERVAL: TimeInterval = { starts_at: '', ends_at: '' };

function clonedIntervals(intervals: TimeInterval[]): TimeInterval[] {
    return intervals.map((interval) => ({ ...interval }));
}

function copiedToEveryDay(intervals: TimeInterval[]): WeeklyHours {
    return {
        1: clonedIntervals(intervals),
        2: clonedIntervals(intervals),
        3: clonedIntervals(intervals),
        4: clonedIntervals(intervals),
        5: clonedIntervals(intervals),
        6: clonedIntervals(intervals),
        7: clonedIntervals(intervals),
    };
}

type Props = {
    value: WeeklyHours;
    onChange: (value: WeeklyHours) => void;
    error?: string;
};

export function WeeklyHoursField({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    const message = fieldMessage({ id: FIELD_ID, error, hint: t('businessSettings.hours.hint') });

    function replaceDay(weekday: WeekdayNumber, intervals: TimeInterval[]): void {
        onChange({ ...value, [weekday]: intervals });
    }

    return (
        <div className="grid gap-2">
            <span id={`${FIELD_ID}-label`} className="text-sm leading-none font-medium">
                {t('businessSettings.hours.label')}
            </span>

            <div
                role="group"
                aria-labelledby={`${FIELD_ID}-label`}
                aria-describedby={message?.id}
                className="rounded-xl border border-border px-4"
            >
                {WEEKDAYS.map((weekday) => (
                    <DayIntervalsRow
                        key={weekday}
                        label={tCommon(WEEKDAY_LABEL_KEYS[weekday])}
                        intervals={value[weekday]}
                        onToggle={(isOpen) =>
                            replaceDay(weekday, isOpen ? [{ ...OPENING_INTERVAL }] : [])
                        }
                        onChangeInterval={(index, interval) =>
                            replaceDay(
                                weekday,
                                value[weekday].map((current, position) =>
                                    position === index ? interval : current,
                                ),
                            )
                        }
                        onAddInterval={() =>
                            replaceDay(weekday, [...value[weekday], { ...BLANK_INTERVAL }])
                        }
                        onRemoveInterval={(index) =>
                            replaceDay(
                                weekday,
                                value[weekday].filter((_, position) => position !== index),
                            )
                        }
                        onCopyToAllDays={() => onChange(copiedToEveryDay(value[weekday]))}
                    />
                ))}
            </div>

            <FieldMessage message={message} />
        </div>
    );
}
