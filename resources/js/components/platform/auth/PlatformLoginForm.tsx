import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { UnderlineField } from '@/components/form/UnderlineField';

export type PlatformLoginFormData = {
    email: string;
    password: string;
};

type Props = {
    form: InertiaFormProps<PlatformLoginFormData>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function PlatformLoginForm({ form, onSubmit }: Props) {
    const { t } = useTranslation('platform');

    return (
        <form onSubmit={onSubmit} className="grid gap-2">
            <UnderlineField
                id="email"
                label={t('login.email')}
                type="email"
                inputMode="email"
                autoComplete="username"
                autoCapitalize="none"
                spellCheck={false}
                autoFocus
                required
                value={form.data.email}
                onChange={(event) => form.setData('email', event.target.value)}
                error={form.errors.email}
            />

            <UnderlineField
                id="password"
                label={t('login.password')}
                autoComplete="current-password"
                required
                value={form.data.password}
                onChange={(event) => form.setData('password', event.target.value)}
                error={form.errors.password}
                reveal={{
                    show: t('login.showPassword'),
                    hide: t('login.hidePassword'),
                }}
            />

            <SubmitButton
                variant="brand"
                size="lg"
                label={t('login.submit')}
                submittingLabel={t('login.submitting')}
                isSubmitting={form.processing}
                className="mt-6 h-12 w-full rounded-xl text-sm"
            />
        </form>
    );
}
