import { Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SettingsPaneBody, SettingsPaneFooter } from '@/components/admin/settings/SettingsPane';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { usePasswordForm } from './use-password-form';

const FORGOT_PASSWORD_URL = '/forgot-password';

type Props = {
    hasPassword: boolean;
    onSaved: () => void;
    onCancel: () => void;
};

export function PasswordForm({ hasPassword, onSaved, onCancel }: Props) {
    const { t } = useTranslation('admin');
    const form = usePasswordForm({ hasPassword, onSaved });

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <SettingsPaneBody className="grid content-start gap-5">
                <div className="grid max-w-sm gap-5">
                    {form.requiresCurrentPassword ? (
                        <div className="grid gap-2">
                            <FormField
                                id="current-password"
                                type="password"
                                autoComplete="current-password"
                                required
                                label={t('security.passwordForm.current.label')}
                                placeholder={t('security.passwordForm.current.placeholder')}
                                value={form.values.currentPassword}
                                onChange={(event) => form.update('currentPassword', event.target.value)}
                                error={form.errorFor('currentPassword')}
                            />

                            <Link
                                href={FORGOT_PASSWORD_URL}
                                className="inline-flex min-h-11 w-fit items-center text-sm font-medium underline underline-offset-4 md:min-h-0"
                            >
                                {t('security.passwordForm.reset')}
                            </Link>
                        </div>
                    ) : null}

                    <FormField
                        id="new-password"
                        type="password"
                        autoComplete="new-password"
                        required
                        label={t('security.passwordForm.new.label')}
                        placeholder={t('security.passwordForm.new.placeholder')}
                        value={form.values.password}
                        onChange={(event) => form.update('password', event.target.value)}
                        hint={t('security.passwordForm.new.hint')}
                        error={form.errorFor('password')}
                    />

                    <FormField
                        id="new-password-confirmation"
                        type="password"
                        autoComplete="new-password"
                        required
                        label={t('security.passwordForm.confirmation.label')}
                        placeholder={t('security.passwordForm.confirmation.placeholder')}
                        value={form.values.passwordConfirmation}
                        onChange={(event) => form.update('passwordConfirmation', event.target.value)}
                        error={form.errorFor('passwordConfirmation')}
                    />
                </div>

                <p className="flex gap-2.5 rounded-lg bg-muted px-4 py-3 text-sm text-foreground/80">
                    <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {t('security.passwordForm.notice')}
                </p>
            </SettingsPaneBody>

            <SettingsPaneFooter>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onCancel}
                    disabled={form.isSubmitting}
                    className="h-11 px-4 md:h-9"
                >
                    {t('security.passwordForm.cancel')}
                </Button>

                <SubmitButton
                    variant="brand"
                    className="h-11 px-5 md:h-9"
                    label={hasPassword ? t('security.passwordForm.update') : t('security.passwordForm.create')}
                    submittingLabel={t('security.passwordForm.saving')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.canSubmit}
                />
            </SettingsPaneFooter>
        </form>
    );
}
