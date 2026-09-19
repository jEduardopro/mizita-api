import { Copy, Plus, X } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { TimePicker } from '@/components/form/TimePicker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { TimeInterval } from '@/lib/booking-brand';

const TIME_FIELD_WIDTH = 'min-w-28 flex-1';

const TIME_FIELD_HEIGHT = 'h-11 md:h-10 md:min-h-10';

type IntervalRowProps = {
    interval: TimeInterval;
    onChange: (interval: TimeInterval) => void;
    onRemove: () => void;
};

function IntervalRow({ interval, onChange, onRemove }: IntervalRowProps) {
    const { t } = useTranslation('admin');
    const rowId = useId();

    const messages = {
        list: t('businessSettings.hours.list'),
        empty: t('businessSettings.hours.empty'),
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className={TIME_FIELD_WIDTH}>
                <TimePicker
                    id={`${rowId}-from`}
                    label={t('businessSettings.hours.from')}
                    value={interval.starts_at}
                    onChange={(value) => onChange({ ...interval, starts_at: value })}
                    messages={messages}
                    className={TIME_FIELD_HEIGHT}
                />
            </div>

            <span aria-hidden="true" className="text-muted-foreground">
                –
            </span>

            <div className={TIME_FIELD_WIDTH}>
                <TimePicker
                    id={`${rowId}-to`}
                    label={t('businessSettings.hours.to')}
                    value={interval.ends_at}
                    onChange={(value) => onChange({ ...interval, ends_at: value })}
                    messages={messages}
                    startsFrom={interval.starts_at === '' ? undefined : interval.starts_at}
                    className={TIME_FIELD_HEIGHT}
                />
            </div>

            <Button
                type="button"
                variant="ghost"
                onClick={onRemove}
                aria-label={t('businessSettings.hours.removeInterval')}
                className="size-11 shrink-0 p-0 md:size-10"
            >
                <X aria-hidden="true" />
            </Button>
        </div>
    );
}

type Props = {
    label: string;
    intervals: TimeInterval[];
    onToggle: (isOpen: boolean) => void;
    onChangeInterval: (index: number, interval: TimeInterval) => void;
    onAddInterval: () => void;
    onRemoveInterval: (index: number) => void;
    onCopyToAllDays: () => void;
};

export function DayIntervalsRow({
    label,
    intervals,
    onToggle,
    onChangeInterval,
    onAddInterval,
    onRemoveInterval,
    onCopyToAllDays,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const switchId = useId();
    const isOpen = intervals.length > 0;

    return (
        <div className="grid gap-3 border-b border-border py-4 last:border-b-0 sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:items-start sm:gap-4">
            <div className="flex items-center gap-3">
                <Switch id={switchId} checked={isOpen} onCheckedChange={onToggle} />

                <Label htmlFor={switchId} className="min-h-11 flex-1 text-base sm:text-sm">
                    {label}
                </Label>
            </div>

            {isOpen ? (
                <div role="group" aria-label={label} className="grid gap-2">
                    {intervals.map((interval, index) => (
                        <IntervalRow
                            key={index}
                            interval={interval}
                            onChange={(changed) => onChangeInterval(index, changed)}
                            onRemove={() => onRemoveInterval(index)}
                        />
                    ))}

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onAddInterval}
                            className="h-11 px-3 text-muted-foreground hover:text-foreground md:h-10"
                        >
                            <Plus aria-hidden="true" />
                            {t('businessSettings.hours.addInterval')}
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onCopyToAllDays}
                            aria-label={t('businessSettings.hours.copyToAllDays', { day: label })}
                            className="size-11 p-0 text-muted-foreground hover:text-foreground md:size-10"
                        >
                            <Copy aria-hidden="true" />
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="flex min-h-11 items-center">
                    <Badge variant="outline" className="h-6 px-2.5">
                        {tCommon('hours.closed')}
                    </Badge>
                </div>
            )}
        </div>
    );
}
