import { useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { PlatformAuthSheet } from '@/components/platform/auth/PlatformAuthSheet';
import {
    PlatformLoginForm,
    type PlatformLoginFormData,
} from '@/components/platform/auth/PlatformLoginForm';
import { AuthTileLayout } from '@/layouts/AuthTileLayout';
import { PLATFORM_LOGIN_URL } from '@/lib/platform-urls';

export default function PlatformLogin() {
    const { t } = useTranslation('platform');
    const queryClient = useQueryClient();
    const form = useForm<PlatformLoginFormData>({
        email: '',
        password: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(PLATFORM_LOGIN_URL, {
            onBefore: () => {
                queryClient.clear();
            },
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthTileLayout title={t('login.title')}>
            <PlatformAuthSheet heading={t('login.heading')} description={t('login.description')}>
                <PlatformLoginForm form={form} onSubmit={submit} />
            </PlatformAuthSheet>
        </AuthTileLayout>
    );
}
