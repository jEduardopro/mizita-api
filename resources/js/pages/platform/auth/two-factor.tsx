import { Link, useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { PlatformAuthSheet } from '@/components/platform/auth/PlatformAuthSheet';
import {
    PlatformTwoFactorForm,
    type PlatformTwoFactorFormData,
} from '@/components/platform/auth/PlatformTwoFactorForm';
import { AuthTileLayout } from '@/layouts/AuthTileLayout';
import { PLATFORM_LOGIN_URL, PLATFORM_TWO_FACTOR_URL } from '@/lib/platform-urls';

export default function PlatformTwoFactor() {
    const { t } = useTranslation('platform');
    const queryClient = useQueryClient();
    const form = useForm<PlatformTwoFactorFormData>({
        code: '',
    });

    function verify() {
        if (form.processing) {
            return;
        }

        form.post(PLATFORM_TWO_FACTOR_URL, {
            onBefore: () => {
                queryClient.clear();
            },
            onError: () => form.reset('code'),
        });
    }

    return (
        <AuthTileLayout title={t('twoFactor.title')}>
            <PlatformAuthSheet
                heading={t('twoFactor.heading')}
                description={t('twoFactor.description')}
                footer={
                    <Link
                        href={PLATFORM_LOGIN_URL}
                        className="inline-flex min-h-11 items-center rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-0"
                    >
                        {t('twoFactor.backToLogin')}
                    </Link>
                }
            >
                <PlatformTwoFactorForm form={form} onVerify={verify} />
            </PlatformAuthSheet>
        </AuthTileLayout>
    );
}
