import { Link, type InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { AuthMethodChoice } from '@/components/auth/AuthMethodChoice';
import { AuthSheet } from '@/components/auth/AuthSheet';
import { LegalLink } from '@/components/auth/LegalLink';
import { FormStatus } from '@/components/form/FormStatus';
import { UnderlineField } from '@/components/form/UnderlineField';
import { Button } from '@/components/ui/button';

/** Exactly what Fortify's login attempt reads. Nothing else. */
export type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

/** Which panel the card is showing. It is card state, not a destination. */
type Mode = 'choice' | 'email';

type Props = {
    form: InertiaFormProps<LoginForm>;
    /** The flash message from the session, e.g. after a password reset. */
    status: string | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

/**
 * The login card, in two panels that swap in place.
 *
 * It asks one question at a time, the same way the signup card does: first how
 * you want to get in, then the two details. The address bar stays on `/login`
 * throughout — there is no page to link to and no state anyone would bookmark,
 * so the panel is a `useState` rather than a route.
 *
 * The form itself belongs to the page. This is the markup and the mode, not the
 * request: Fortify decides what a valid attempt is, and its message arrives
 * through `form.errors`.
 */
export function LoginCard({ form, status, onSubmit }: Props) {
    const { t } = useTranslation('auth');

    // Errors mean someone has already tried, so the card opens on the panel they
    // tried from. Inertia keeps component state when validation fails on the same
    // page, so this is the guard for any path that does remount — without it a
    // "wrong credentials" message would sit behind the choice panel, unread.
    const [mode, setMode] = useState<Mode>(() =>
        Object.keys(form.errors).length > 0 ? 'email' : 'choice',
    );

    return (
        <AuthSheet
            heading={t('login.heading')}
            description={t('login.description')}
            footer={
                <>
                    {t('login.footer.prompt')}{' '}
                    <Link
                        href="/register"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('login.footer.link')}
                    </Link>
                </>
            }
            legal={
                <>
                    {/*
                     * `Trans` keeps the sentence whole. Splitting it into a
                     * prefix, two link labels and a joiner would hard-code
                     * Spanish word order into the markup, and the first language
                     * that puts the verb elsewhere would have nowhere to put it.
                     */}
                    <p className="text-balance">
                        <Trans
                            i18nKey="login.legal"
                            ns="auth"
                            components={{
                                terms: <LegalLink />,
                                privacy: <LegalLink />,
                            }}
                        />
                    </p>
                    <p className="mt-2">
                        {t('shell.copyright', { year: new Date().getFullYear() })}
                    </p>
                </>
            }
        >
            <div className="grid gap-5">
                {/*
                 * Above both panels, not inside the form. The flash that
                 * matters most here arrives after a password reset, which is a
                 * fresh visit: the card mounts on the choice panel, and a
                 * message parked inside the email panel would never be seen.
                 */}
                <FormStatus message={status} />

                <div
                    key={mode}
                    className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
                >
                    {mode === 'choice' ? (
                        <AuthMethodChoice
                            google={t('login.methods.google')}
                            googleSoon={t('login.methods.googleSoon')}
                            divider={t('login.divider')}
                            email={t('login.methods.email')}
                            onEmail={() => setMode('email')}
                        />
                    ) : (
                        <form onSubmit={onSubmit} className="grid gap-2">
                            <UnderlineField
                                id="email"
                                label={t('fields.email')}
                                type="email"
                                autoComplete="username"
                                autoFocus
                                required
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                error={form.errors.email}
                            />

                            <UnderlineField
                                id="password"
                                label={t('fields.password')}
                                autoComplete="current-password"
                                required
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                error={form.errors.password}
                                reveal={{
                                    show: t('fields.showPassword'),
                                    hide: t('fields.hidePassword'),
                                }}
                            />

                            {/*
                             * One line in a 28rem card, even in Spanish,
                             * which is the longer of the two labels. The
                             * wrap is the net for the phone, where the card
                             * is as wide as the screen allows and the pair
                             * no longer fits: wrapping rather than shrinking
                             * is what sends the whole link down to its own
                             * line instead of breaking either phrase in two.
                             */}
                            <div className="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                <label
                                    htmlFor="remember"
                                    className="flex items-center gap-2 text-sm text-muted-foreground select-none"
                                >
                                    <input
                                        id="remember"
                                        type="checkbox"
                                        className="size-4 rounded-sm border-input accent-foreground outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                        checked={form.data.remember}
                                        onChange={(event) =>
                                            form.setData('remember', event.target.checked)
                                        }
                                    />
                                    {t('login.remember')}
                                </label>

                                <Link
                                    href="/forgot-password"
                                    className="rounded-sm text-sm text-muted-foreground underline underline-offset-4 outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                                >
                                    {t('login.forgotPassword')}
                                </Link>
                            </div>

                            <Button
                                type="submit"
                                variant="brand"
                                size="lg"
                                disabled={form.processing}
                                className="mt-4 h-12 w-full rounded-xl text-sm"
                            >
                                {form.processing ? t('login.submitting') : t('login.submit')}
                            </Button>

                            <button
                                type="button"
                                onClick={() => setMode('choice')}
                                className="mx-auto mt-1 rounded-sm text-xs text-muted-foreground underline underline-offset-4 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                            >
                                {t('login.methods.back')}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </AuthSheet>
    );
}
