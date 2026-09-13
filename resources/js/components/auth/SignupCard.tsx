import { Link, usePage, type InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { AuthCard } from '@/components/auth/AuthCard';
import { AuthMethodChoice } from '@/components/auth/AuthMethodChoice';
import { LegalLink } from '@/components/auth/LegalLink';
import { UnderlineField } from '@/components/form/UnderlineField';
import { Button } from '@/components/ui/button';

/** Exactly the payload Fortify's `CreateNewUser` validates. Nothing else. */
export type SignupForm = {
    name: string;
    email: string;
    password: string;
};

/** Which panel the card is showing. It is card state, not a destination. */
type Mode = 'choice' | 'email';

type Props = {
    form: InertiaFormProps<SignupForm>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

/**
 * The signup card, in two panels that swap in place.
 *
 * The card asks one question at a time. The first panel asks how you want to
 * sign up; the second takes the three details. It is a `useState`, not a route
 * and not an Inertia prop, because nothing about it is worth a URL: there is no
 * page to link to, no state anyone would bookmark, and no server round trip
 * between the two. The address bar stays on `/register` throughout.
 *
 * A visitor can go back. `Usar otro método` returns to the choice, which is what
 * the reference's row of small social buttons does in its second state — with a
 * single alternative, a row holding one icon reads as a stray control, so the way
 * back is a link instead.
 *
 * The panels are keyed on the mode so each one re-enters, and the name field is
 * `autoFocus`: it mounts only when someone has just asked for the form, so the
 * cursor lands where they were about to type. On first paint the panel is never
 * mounted, so nothing is stolen from the page.
 *
 * The form itself belongs to the page — this is the markup and the mode, not the
 * request. Validation is the server's: `CreateNewUser` is the only definition of
 * what is valid, and its messages arrive through `form.errors`.
 */
export function SignupCard({ form, onSubmit }: Props) {
    const { t } = useTranslation('auth');
    const [mode, setMode] = useState<Mode>('choice');

    // A failed Google callback comes back as `errors.google`, a shared page prop
    // rather than one of this form's fields. The server's string is the signal,
    // not the copy: the card says it in its own voice, in the language i18next is
    // rendering the rest of the panel in.
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

                            {/*
                             * The minimum is stated up front because it is the
                             * only rule `Password::default()` enforces, and a
                             * round trip to learn a number we already know is a
                             * round trip we can spend on nothing.
                             *
                             * It is a hint, not a `minLength`. A native constraint
                             * would block the submit with a bubble written in the
                             * browser's language, next to a form that is answering
                             * in the locale Laravel resolved — two languages
                             * disagreeing about the same field. The server stays
                             * the authority, and it replies in Spanish.
                             */}
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

            {/*
             * Outside the card, the way the reference has it: it is the condition
             * attached to the action, not part of the form.
             *
             * `Trans` keeps the sentence whole. Splitting it into a prefix, two
             * link labels and a joiner would hard-code Spanish word order into
             * the markup, and the first language that puts the verb elsewhere
             * would have nowhere to put it.
             *
             */}
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
