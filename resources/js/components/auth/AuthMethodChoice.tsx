import { useId } from 'react';
import { GoogleIcon } from '@/components/auth/GoogleIcon';
import { Button } from '@/components/ui/button';

type Props = {
    /** The Google button's label. */
    google: string;
    /** Why that button cannot be pressed yet. */
    googleSoon: string;
    /** The one word between the two alternatives. */
    divider: string;
    /** The label of the button that opens the email form. */
    email: string;
    /** Shows the email form. The panel itself knows nothing about what follows. */
    onEmail: () => void;
};

/**
 * The first panel of an auth card: pick how you want to get in.
 *
 * Every string arrives pre-translated. The card above it owns the copy, so this
 * component serves login and registration without either screen's namespace
 * leaking into it — the same rule `components/form/` follows.
 */
export function AuthMethodChoice({ google, googleSoon, divider, email, onEmail }: Props) {
    const soonId = useId();

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                {/*
                 * UI only. There is no Socialite, no provider config and no route
                 * behind this, so it is disabled rather than linked anywhere — a
                 * button that goes nowhere is worse than one that says it is not
                 * ready. `aria-describedby` ties it to the note so the reason is
                 * announced with it.
                 */}
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    disabled
                    aria-describedby={soonId}
                    className="h-12 w-full gap-3 rounded-xl text-sm"
                >
                    <GoogleIcon className="size-5" />
                    {google}
                </Button>

                <p id={soonId} className="text-center text-xs text-muted-foreground">
                    {googleSoon}
                </p>
            </div>

            <Divider label={divider} />

            <Button
                type="button"
                variant="brand"
                size="lg"
                onClick={onEmail}
                className="h-12 w-full rounded-xl text-sm"
            >
                {email}
            </Button>
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
