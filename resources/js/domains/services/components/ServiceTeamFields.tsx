import { useTranslation } from 'react-i18next';
import {
    CheckboxListField,
    type CheckboxListOption,
    type CheckboxListStatus,
} from '@/components/form/CheckboxListField';
import type { ServiceFormController } from './use-service-form';

function optionsStatusFrom(isPending: boolean, isError: boolean): CheckboxListStatus {
    if (isPending) {
        return 'pending';
    }

    return isError ? 'error' : 'ready';
}

export type StaffChoices = {
    options: readonly CheckboxListOption[];
    isPending: boolean;
    isError: boolean;
    refetch: () => void;
};

type Props = {
    form: ServiceFormController;
    staff: StaffChoices;
};

export function ServiceTeamFields({ form, staff }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <div className="grid content-start gap-5">
            <CheckboxListField
                id="service-staff"
                label={t('services.form.staff.label')}
                options={staff.options}
                value={form.values.staffIds}
                onChange={(value) => form.update('staffIds', value)}
                optionsStatus={optionsStatusFrom(staff.isPending, staff.isError)}
                onRetryOptions={staff.refetch}
                required
                hint={t('services.form.staff.hint')}
                error={form.errorFor('staffIds')}
                messages={{
                    searchLabel: t('services.form.staff.searchLabel'),
                    searchPlaceholder: t('services.form.staff.searchPlaceholder'),
                    selectAll: t('services.form.staff.selectAll'),
                    selected: t('services.form.staff.selected', {
                        count: form.values.staffIds.length,
                    }),
                    empty: t('services.form.staff.empty'),
                    optionsError: t('services.form.staff.error'),
                    retry: tCommon('actions.tryAgain'),
                }}
            />

            <label className="flex items-start gap-3 rounded-xl border border-input p-3">
                <input
                    type="checkbox"
                    checked={form.values.active}
                    onChange={(event) => form.update('active', event.target.checked)}
                    className="mt-0.5 size-4.5 accent-primary"
                />

                <span className="grid gap-1">
                    <span className="text-sm font-medium">
                        {t('services.form.visibility.label')}
                    </span>

                    <span className="text-xs text-muted-foreground">
                        {t('services.form.visibility.hint')}
                    </span>
                </span>
            </label>
        </div>
    );
}
