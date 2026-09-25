import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';

const ACTION_SIZE = 'h-11 px-4 md:h-9';

type Props = {
    formId: string;
    label: string;
    submittingLabel: string;
    isSubmitting: boolean;
    canSubmit: boolean;
    onCancel: () => void;
};

export function TeamDialogActions({
    formId,
    label,
    submittingLabel,
    isSubmitting,
    canSubmit,
    onCancel,
}: Props) {
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <Button type="button" variant="outline" onClick={onCancel} className={ACTION_SIZE}>
                {tCommon('actions.cancel')}
            </Button>

            <SubmitButton
                form={formId}
                variant="brand"
                className={ACTION_SIZE}
                label={label}
                submittingLabel={submittingLabel}
                isSubmitting={isSubmitting}
                disabled={! canSubmit}
            />
        </>
    );
}
