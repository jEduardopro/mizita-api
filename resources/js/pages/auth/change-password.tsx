import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { useChangePasswordForm } from '@/domains/accounts/components/use-change-password-form';
import { useLogOut } from '@/hooks/use-log-out';
import { AuthLayout } from '@/layouts/AuthLayout';

const CALENDAR_URL = '/calendar';

export default function ChangePassword() {
    const { t } = useTranslation('auth');
    const logOut = useLogOut();
    const form = useChangePasswordForm({ onChanged: () => router.visit(CALENDAR_URL) });

    return (
        <AuthLayout
            title={t('changePassword.title')}
            heading={t('changePassword.heading')}
            description={t('changePassword.description')}
            footer={
                <p>
                    {t('changePassword.footer.prompt')}{' '}
                    <button
                        type="button"
                        onClick={logOut}
                        className="inline-flex min-h-11 items-center rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-0"
                    >
                        {t('changePassword.footer.link')}
                    </button>
                </p>
            }
        >
            <form onSubmit={form.submit} className="grid gap-5">
                <FormField
                    id="password"
                    label={t('fields.newPassword')}
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    required
                    value={form.values.password}
                    onChange={(event) => form.update('password', event.target.value)}
                    hint={t('changePassword.hint')}
                    error={form.errorFor('password')}
                />

                <FormField
                    id="password_confirmation"
                    label={t('fields.newPasswordConfirmation')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.values.passwordConfirmation}
                    onChange={(event) => form.update('passwordConfirmation', event.target.value)}
                    error={form.errorFor('passwordConfirmation')}
                />

                <SubmitButton
                    size="lg"
                    label={t('changePassword.submit')}
                    submittingLabel={t('changePassword.submitting')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.canSubmit}
                />
            </form>
        </AuthLayout>
    );
}
