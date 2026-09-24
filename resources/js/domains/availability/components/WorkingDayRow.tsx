import { useId, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { TimePicker } from '@/components/form/TimePicker';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { WorkingDay, WorkingInterval } from './working-week';

const TIME_FIELD = 'min-w-0 flex-1 sm:w-32 sm:flex-none';

const TIME_CONTROL = 'h-11 md:h-10 md:min-h-10';

type Props = {
    label: string;
    day: WorkingDay;
    onToggle: (enabled: boolean) => void;
    onChange: (interval: WorkingInterval) => void;
    trailing?: ReactNode;
};

export function WorkingDayRow({ label, day, onToggle, onChange, trailing }: Props) {
    const { t } = useTranslation('admin');
    const rowId = useId();

    const messages = {
        list: t('workingHours.list'),
        empty: t('workingHours.empty'),
    };

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-border px-4 py-3 last:border-b-0">
            <div className="flex min-w-36 flex-1 items-center gap-3">
                <Switch id={`${rowId}-switch`} checked={day.enabled} onCheckedChange={onToggle} />

                <Label
                    htmlFor={`${rowId}-switch`}
                    className="min-h-11 flex-1 text-base font-normal sm:text-sm"
                >
                    {label}
                </Label>
            </div>

            {day.enabled ? (
                <div role="group" aria-label={label} className="flex w-full items-center gap-2 sm:w-auto">
                    <div className={TIME_FIELD}>
                        <TimePicker
                            id={`${rowId}-from`}
                            label={t('workingHours.from', { day: label })}
                            value={day.starts_at}
                            onChange={(value) => onChange({ starts_at: value, ends_at: day.ends_at })}
                            messages={messages}
                            className={TIME_CONTROL}
                        />
                    </div>

                    <span aria-hidden="true" className="text-muted-foreground">
                        –
                    </span>

                    <div className={TIME_FIELD}>
                        <TimePicker
                            id={`${rowId}-to`}
                            label={t('workingHours.to', { day: label })}
                            value={day.ends_at}
                            onChange={(value) => onChange({ starts_at: day.starts_at, ends_at: value })}
                            messages={messages}
                            startsFrom={day.starts_at === '' ? undefined : day.starts_at}
                            className={TIME_CONTROL}
                        />
                    </div>

                    {trailing ?? <span aria-hidden="true" className="hidden size-11 shrink-0 sm:block md:size-10" />}
                </div>
            ) : (
                <Badge variant="secondary" className="h-7 rounded-md px-2.5 font-normal">
                    {t('workingHours.dayOff')}
                </Badge>
            )}
        </div>
    );
}
