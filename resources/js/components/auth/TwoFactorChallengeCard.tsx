import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { OTP_LENGTH, OtpField } from '@/components/form/OtpField';
import { SubmitButton } from '@/components/form/SubmitButton';

export type TwoFactorMethod = 'code' | 'recovery';

export type TwoFactorChallengeForm = {
    code: string;
    recovery_code: string;
};

type Props = {
    form: InertiaFormProps<TwoFactorChallengeForm>;
    method: TwoFactorMethod;
    onChallenge: () => void;
    onSwitchMethod: () => void;
};

export function TwoFactorChallengeCard({ form, method, onChallenge, onSwitchMethod }: Props) {
    const { t } = useTranslation('auth');

    const isComplete = method === 'code'
        ? form.data.code.length === OTP_LENGTH
        : form.data.recovery_code.trim() !== '';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        onChallenge();
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            {method === 'code' ? (
                <OtpField
                    id="code"
                    label={t('twoFactorChallenge.code')}
                    autoFocus
                    value={form.data.code}
                    onChange={(code) => form.setData('code', code)}
                    onComplete={onChallenge}
                    error={form.errors.code}
                />
            ) : (
                <FormField
                    id="recovery_code"
                    label={t('twoFactorChallenge.recoveryCode')}
                    autoComplete="one-time-code"
                    autoCapitalize="none"
                    autoCorrect="off"
                    spellCheck={false}
                    autoFocus
                    required
                    value={form.data.recovery_code}
                    onChange={(event) => form.setData('recovery_code', event.target.value)}
                    error={form.errors.recovery_code}
                />
            )}

            <SubmitButton
                size="lg"
                label={t('twoFactorChallenge.submit')}
                submittingLabel={t('twoFactorChallenge.submitting')}
                isSubmitting={form.processing}
                disabled={! isComplete}
            />

            <button
                type="button"
                onClick={onSwitchMethod}
                className="mx-auto inline-flex min-h-11 items-center rounded-sm text-sm text-muted-foreground underline underline-offset-4 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                {method === 'code' ? t('twoFactorChallenge.useRecovery') : t('twoFactorChallenge.useCode')}
            </button>
        </form>
    );
}
