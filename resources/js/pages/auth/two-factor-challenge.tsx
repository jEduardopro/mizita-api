import { Link, useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    TwoFactorChallengeCard,
    type TwoFactorChallengeForm,
    type TwoFactorMethod,
} from '@/components/auth/TwoFactorChallengeCard';
import { AuthLayout } from '@/layouts/AuthLayout';

const CHALLENGE_URL = '/two-factor-challenge';

const LOGIN_URL = '/login';

const METHOD_DESCRIPTION_KEYS = {
    code: 'twoFactorChallenge.body',
    recovery: 'twoFactorChallenge.recoveryBody',
} as const;

export default function TwoFactorChallenge() {
    const { t } = useTranslation('auth');
    const queryClient = useQueryClient();
    const [method, setMethod] = useState<TwoFactorMethod>('code');
    const form = useForm<TwoFactorChallengeForm>({
        code: '',
        recovery_code: '',
    });

    function challenge() {
        if (form.processing) {
            return;
        }

        form.transform((data) =>
            method === 'code' ? { code: data.code } : { recovery_code: data.recovery_code },
        );

        form.post(CHALLENGE_URL, {
            onBefore: () => {
                queryClient.clear();
            },
            onError: () => form.reset('code'),
        });
    }

    function switchMethod() {
        form.resetAndClearErrors();
        setMethod((current) => (current === 'code' ? 'recovery' : 'code'));
    }

    return (
        <AuthLayout
            title={t('twoFactorChallenge.title')}
            heading={t('twoFactorChallenge.heading')}
            description={t(METHOD_DESCRIPTION_KEYS[method])}
            footer={
                <Link
                    href={LOGIN_URL}
                    className="inline-flex min-h-11 items-center rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-0"
                >
                    {t('twoFactorChallenge.backToLogin')}
                </Link>
            }
        >
            <TwoFactorChallengeCard
                form={form}
                method={method}
                onChallenge={challenge}
                onSwitchMethod={switchMethod}
            />
        </AuthLayout>
    );
}
