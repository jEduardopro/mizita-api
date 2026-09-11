import { Link, type InertiaFormProps } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { GoogleIcon } from '@/components/auth/GoogleIcon';
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
 * the reference's row of small social buttons does in its second state — with one
 * method and that method disabled, a row of one dead icon reads as a bug, so the
 * way back is a link instead.
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

    return (
        <div className="mx-auto w-full max-w-md lg:max-w-none">
            <div className="rounded-2xl border border-border bg-card p-6 shadow-xl shadow-foreground/5 sm:p-8 dark:shadow-black/30">
                <h2 className="font-heading text-lg font-medium tracking-[-0.02em]">
                    {t('register.heading')}
                </h2>

                <div
                    key={mode}
                    className="mt-6 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:duration-200"
                >
                    {mode === 'choice' ? (
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                {/*
                                 * UI only. There is no Socialite, no provider
                                 * config and no route behind this, so it is
                                 * disabled rather than linked anywhere — a button
                                 * that goes nowhere is worse than one that says
                                 * it is not ready. `aria-describedby` ties it to
                                 * the note so the reason is announced with it.
                                 */}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    disabled
                                    aria-describedby="google-soon"
                                    className="h-12 w-full gap-3 rounded-xl text-sm"
                                >
                                    <GoogleIcon className="size-5" />
                                    {t('register.methods.google')}
                                </Button>

                                <p
                                    id="google-soon"
                                    className="text-center text-xs text-muted-foreground"
                                >
                                    {t('register.methods.googleSoon')}
                                </p>
                            </div>

                            <Divider label={t('register.divider')} />

                            <Button
                                type="button"
                                variant="brand"
                                size="lg"
                                onClick={() => setMode('email')}
                                className="h-12 w-full rounded-xl text-sm"
                            >
                                {t('register.methods.email')}
                            </Button>
                        </div>
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

                <p className="mt-6 border-t border-border pt-5 text-center text-sm text-muted-foreground">
                    {t('register.footer.prompt')}{' '}
                    <Link
                        href="/login"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('register.footer.link')}
                    </Link>
                </p>
            </div>

            {/*
             * Outside the card, the way the reference has it: it is the condition
             * attached to the action, not part of the form.
             *
             * `Trans` keeps the sentence whole. Splitting it into a prefix, two
             * link labels and a joiner would hard-code Spanish word order into
             * the markup, and the first language that puts the verb elsewhere
             * would have nowhere to put it.
             *
             * PLACEHOLDER: both destinations are `#`, matching `PublicLayout`'s
             * footer. Each one is a route `mizita-backend` has yet to add.
             */}
            <p className="mt-5 text-center text-xs leading-relaxed text-balance text-muted-foreground">
                <Trans
                    i18nKey="register.legal"
                    ns="auth"
                    components={{
                        terms: <LegalLink />,
                        privacy: <LegalLink />,
                    }}
                />
            </p>
        </div>
    );
}

type DividerProps = { label: string };

/**
 * A hairline with one word sitting in it. The word is content, not decoration —
 * it says the two things above and below it are alternatives rather than steps.
 */
function Divider({ label }: DividerProps) {
    return (
        <div className="flex items-center gap-3" role="separator" aria-label={label}>
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
            <span aria-hidden="true" className="text-xs text-muted-foreground">
                {label}
            </span>
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
        </div>
    );
}

/**
 * The anchor `Trans` clones for each tag in the legal sentence. It carries no
 * text of its own: the label comes from inside the translated string, which is
 * the only place a translator can reach it.
 */
function LegalLink({ children }: { children?: ReactNode }) {
    return (
        <a
            href="#"
            className="rounded-sm underline underline-offset-2 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            {children}
        </a>
    );
}
