import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { OTP_LENGTH, OtpField } from '@/components/form/OtpField';
import { SubmitButton } from '@/components/form/SubmitButton';

export type PlatformTwoFactorFormData = {
    code: string;
};

type Props = {
    form: InertiaFormProps<PlatformTwoFactorFormData>;
    onVerify: () => void;
};

export function PlatformTwoFactorForm({ form, onVerify }: Props) {
    const { t } = useTranslation('platform');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        onVerify();
    }

    return (
        <form onSubmit={submit} className="grid gap-6">
            <OtpField
                id="code"
                label={t('twoFactor.code')}
                autoFocus
                value={form.data.code}
                onChange={(code) => form.setData('code', code)}
                onComplete={onVerify}
                error={form.errors.code}
            />

            <SubmitButton
                variant="brand"
                size="lg"
                label={t('twoFactor.submit')}
                submittingLabel={t('twoFactor.submitting')}
                isSubmitting={form.processing}
                disabled={form.data.code.length !== OTP_LENGTH}
                className="h-12 w-full rounded-xl text-sm"
            />
        </form>
    );
}
