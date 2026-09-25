import { cn } from 'cn';
import { ChevronDown, Pencil } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import { WEEKDAYS } from '@/lib/booking-brand';
import { isoWeekdayIn, timezoneAbbreviationIn } from '@/lib/timezone';
import { WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';
import type { ScheduleRule } from '../types';
import { formatScheduleRules } from './schedule-format';
import { rulesOn } from './working-week';

const ICON_BUTTON = 'size-11 shrink-0 p-0 text-muted-foreground hover:text-foreground md:size-9';

type Props = {
    schedule: readonly ScheduleRule[] | undefined;
    loadFailed: boolean;
    onRetry: () => void;
    timezone: string | null;
    onEdit?: () => void;
};

export function WorkingHoursSummary({ schedule, loadFailed, onRetry, timezone, onEdit }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const [isExpanded, setIsExpanded] = useState(false);

    if (loadFailed) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-muted-foreground">{t('workingHours.loadError')}</span>

                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-3 md:h-8">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        );
    }

    if (schedule === undefined || timezone === null) {
        return <Skeleton className="h-5 w-56" />;
    }

    const now = new Date();
    const today = isoWeekdayIn(timezone, now);
    const abbreviation = timezoneAbbreviationIn(timezone, now);
    const todayRules = today === null ? [] : rulesOn(schedule, today);
    const todayHours =
        todayRules.length === 0 ? t('workingHours.dayOff') : formatScheduleRules(todayRules);

    return (
        <Collapsible open={isExpanded} onOpenChange={setIsExpanded} className="grid gap-2">
            <div className="flex flex-wrap items-center gap-x-1">
                <span className="mr-1">
                    {t('workingHours.summary.today', { hours: todayHours })}

                    {abbreviation === null ? null : (
                        <span className="ml-1.5 text-muted-foreground">({abbreviation})</span>
                    )}
                </span>

                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        aria-label={
                            isExpanded ? t('workingHours.summary.collapse') : t('workingHours.summary.expand')
                        }
                        className={ICON_BUTTON}
                    >
                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'motion-safe:transition-transform motion-safe:duration-200',
                                isExpanded ? 'rotate-180' : undefined,
                            )}
                        />
                    </Button>
                </CollapsibleTrigger>

                {onEdit === undefined ? null : (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onEdit}
                        aria-label={t('workingHours.summary.edit')}
                        className={ICON_BUTTON}
                    >
                        <Pencil aria-hidden="true" />
                    </Button>
                )}
            </div>

            <CollapsibleContent>
                <dl className="grid max-w-sm gap-1.5 pb-1">
                    {WEEKDAYS.map((weekday) => {
                        const rules = rulesOn(schedule, weekday);
                        const isToday = weekday === today;

                        return (
                            <div
                                key={weekday}
                                className={cn(
                                    'grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3',
                                    isToday ? 'font-medium text-foreground' : 'text-muted-foreground',
                                )}
                            >
                                <dt>{tCommon(WEEKDAY_LABEL_KEYS[weekday])}</dt>

                                <dd className="tabular-nums">
                                    {rules.length === 0
                                        ? t('workingHours.dayOff')
                                        : formatScheduleRules(rules)}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </CollapsibleContent>
        </Collapsible>
    );
}
