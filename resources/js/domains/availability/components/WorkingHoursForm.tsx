import { CopyCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody, SettingsPaneFooter } from '@/components/admin/settings/SettingsPane';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { WEEKDAYS } from '@/lib/booking-brand';
import { WEEKDAY_IN_SENTENCE_LABEL_KEYS, WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';
import type { MySchedule, ReplaceSchedulePayload, ScheduleRule } from '../types';
import { useWorkingHoursForm } from './use-working-hours-form';
import { WorkingDayRow } from './WorkingDayRow';

const FIRST_WEEKDAY = WEEKDAYS[0];

type Props = {
    schedule: MySchedule;
    businessSchedule: readonly ScheduleRule[] | null;
    onSave: (payload: ReplaceSchedulePayload) => Promise<unknown>;
    onCancel?: () => void;
    notice?: ReactNode;
};

export function WorkingHoursForm({ schedule, businessSchedule, onSave, onCancel, notice }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const form = useWorkingHoursForm({ schedule, businessSchedule, onSave });

    function cancel() {
        form.discard();
        onCancel?.();
    }

    const firstDay = form.week[FIRST_WEEKDAY];

    const copyButton = (
        <Button
            type="button"
            variant="ghost"
            onClick={() => form.copyToActiveDays(FIRST_WEEKDAY)}
            aria-label={t('workingHours.copyToAll', {
                day: tCommon(WEEKDAY_IN_SENTENCE_LABEL_KEYS[FIRST_WEEKDAY]),
            })}
            className="size-11 shrink-0 p-0 text-muted-foreground hover:text-foreground md:size-9"
        >
            <CopyCheck aria-hidden="true" />
        </Button>
    );

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <SettingsPaneBody className="grid content-start gap-4 md:gap-3">
                <div className="grid gap-1">
                    <p className="text-sm text-muted-foreground">{t('workingHours.description')}</p>

                    {schedule.inherited ? (
                        <p className="text-sm text-muted-foreground">{t('workingHours.inherited')}</p>
                    ) : null}
                </div>

                <div className="rounded-xl border border-border">
                    {WEEKDAYS.map((weekday) => (
                        <WorkingDayRow
                            key={weekday}
                            label={tCommon(WEEKDAY_LABEL_KEYS[weekday])}
                            day={form.week[weekday]}
                            onToggle={(enabled) => form.toggleDay(weekday, enabled)}
                            onChange={(interval) => form.changeDay(weekday, interval)}
                            trailing={weekday === FIRST_WEEKDAY && firstDay.enabled ? copyButton : undefined}
                        />
                    ))}
                </div>

                {notice}
            </SettingsPaneBody>

            <SettingsPaneFooter>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={cancel}
                    disabled={form.isSubmitting}
                    className="h-11 px-4 md:h-9"
                >
                    {t('workingHours.cancel')}
                </Button>

                <SubmitButton
                    variant="brand"
                    className="h-11 px-5 md:h-9"
                    label={t('workingHours.save')}
                    submittingLabel={t('workingHours.saving')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.isDirty}
                />
            </SettingsPaneFooter>
        </form>
    );
}
