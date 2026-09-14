import { Link, usePage, type InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { AuthCard } from '@/components/auth/AuthCard';
import { AuthMethodChoice } from '@/components/auth/AuthMethodChoice';
import { LegalLink } from '@/components/auth/LegalLink';
import { UnderlineField } from '@/components/form/UnderlineField';
import { Button } from '@/components/ui/button';

export type SignupForm = {
    name: string;
    email: string;
    password: string;
};

type Mode = 'choice' | 'email';

type Props = {
    form: InertiaFormProps<SignupForm>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function SignupCard({ form, onSubmit }: Props) {
    const { t } = useTranslation('auth');
    const [mode, setMode] = useState<Mode>('choice');

    const googleFailed = Boolean(usePage().props.errors.google);

    return (
        <div className="mx-auto w-full max-w-md lg:max-w-none">
            <AuthCard
                heading={t('register.heading')}
                footer={
                    <>
                        {t('register.footer.prompt')}{' '}
                        <Link
                            href="/login"
                            className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            {t('register.footer.link')}
                        </Link>
                    </>
                }
            >
                <div
                    key={mode}
                    className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
                >
                    {mode === 'choice' ? (
                        <AuthMethodChoice
                            google={t('register.methods.google')}
                            googleHref="/auth/google/redirect"
                            googleError={
                                googleFailed ? t('register.methods.googleFailed') : undefined
                            }
                            divider={t('register.divider')}
                            email={t('register.methods.email')}
                            onEmail={() => setMode('email')}
                        />
                    ) : (
                        <form onSubmit={onSubmit} className="grid gap-2">
                            <UnderlineField
                                id="name"
                                label={t('fields.fullName')}
                                autoComplete="name"
                                autoFocus
                                required
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                error={form.errors.name}
                            />

                            <UnderlineField
                                id="email"
                                label={t('fields.email')}
                                type="email"
                                autoComplete="username"
                                required
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                error={form.errors.email}
                            />

                            <UnderlineField
                                id="password"
                                label={t('fields.password')}
                                autoComplete="new-password"
                                required
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                hint={t('register.passwordHint')}
                                error={form.errors.password}
                                reveal={{
                                    show: t('fields.showPassword'),
                                    hide: t('fields.hidePassword'),
                                }}
                            />

                            <Button
                                type="submit"
                                variant="brand"
                                size="lg"
                                disabled={form.processing}
                                className="mt-4 h-12 w-full rounded-xl text-sm"
                            >
                                {form.processing
                                    ? t('register.submitting')
                                    : t('register.submit')}
                            </Button>

                            <button
                                type="button"
                                onClick={() => setMode('choice')}
                                className="mx-auto mt-1 rounded-sm text-xs text-muted-foreground underline underline-offset-4 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                {t('register.methods.back')}
                            </button>
                        </form>
                    )}
                </div>
            </AuthCard>

            <p className="mt-5 text-center text-xs leading-relaxed text-balance text-muted-foreground">
                <Trans
                    i18nKey="register.legal"
                    ns="auth"
                    components={{
                        terms: <LegalLink document="terms" />,
                        privacy: <LegalLink document="privacy" />,
                    }}
                />
            </p>
        </div>
    );
}
