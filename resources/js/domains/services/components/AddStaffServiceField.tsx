import { useTranslation } from 'react-i18next';
import {
    ComboboxField,
    type ComboboxOption,
    type ComboboxOptionsStatus,
} from '@/components/form/ComboboxField';

const FIELD_ID = 'add-staff-service';

type Props = {
    options: readonly ComboboxOption[];
    optionsStatus: ComboboxOptionsStatus;
    onRetryOptions: () => void;
    onSelect: (serviceId: string) => void;
    disabled: boolean;
};

export function AddStaffServiceField({
    options,
    optionsStatus,
    onRetryOptions,
    onSelect,
    disabled,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <div className="max-w-sm">
            <ComboboxField
                id={FIELD_ID}
                label={t('staffServices.add.label')}
                placeholder={t('staffServices.add.placeholder')}
                options={options}
                value={null}
                onChange={(serviceId) => {
                    if (serviceId !== null) {
                        onSelect(serviceId);
                    }
                }}
                optionsStatus={optionsStatus}
                onRetryOptions={onRetryOptions}
                disabled={disabled}
                messages={{
                    empty: t('staffServices.add.empty'),
                    results: t('staffServices.add.results'),
                    optionsError: t('staffServices.add.optionsError'),
                    retry: tCommon('actions.tryAgain'),
                }}
            />
        </div>
    );
}
