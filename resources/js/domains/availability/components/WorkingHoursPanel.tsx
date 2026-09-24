import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { WEEKDAYS } from '@/lib/booking-brand';
import type { MySchedule, ReplaceSchedulePayload, ScheduleRule } from '../types';
import { WorkingHoursForm } from './WorkingHoursForm';

type Props = {
    schedule: MySchedule | undefined;
    loadFailed: boolean;
    onRetry: () => void;
    businessSchedule: readonly ScheduleRule[] | null;
    onSave: (payload: ReplaceSchedulePayload) => Promise<unknown>;
    onCancel?: () => void;
    notice?: ReactNode;
};

function WorkingHoursSkeleton() {
    return (
        <SettingsPaneBody className="grid content-start gap-4">
            <div role="status" aria-busy="true" className="grid gap-4">
                <Skeleton className="h-4 w-3/4" />

                <div className="grid gap-px overflow-hidden rounded-xl border border-border">
                    {WEEKDAYS.map((weekday) => (
                        <Skeleton key={weekday} className="h-16 rounded-none md:h-13" />
                    ))}
                </div>
            </div>
        </SettingsPaneBody>
    );
}

type LoadErrorProps = {
    onRetry: () => void;
};

function WorkingHoursLoadError({ onRetry }: LoadErrorProps) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <SettingsPaneBody>
            <div role="alert" className="grid justify-items-start gap-3 rounded-xl border border-border p-5">
                <p className="text-sm font-medium">{t('workingHours.loadError')}</p>

                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        </SettingsPaneBody>
    );
}

export function WorkingHoursPanel({ schedule, loadFailed, onRetry, ...form }: Props) {
    if (schedule !== undefined) {
        return <WorkingHoursForm schedule={schedule} {...form} />;
    }

    if (loadFailed) {
        return <WorkingHoursLoadError onRetry={onRetry} />;
    }

    return <WorkingHoursSkeleton />;
}
