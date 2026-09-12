import { GoogleIcon } from '@/components/auth/GoogleIcon';
import { Button } from '@/components/ui/button';

type Props = {
    /** The Google button's label. */
    google: string;
    /** Where the Google flow starts. A server route, not an API endpoint. */
    googleHref: string;
    /** What to say when a Google attempt came back without a session. */
    googleError?: string;
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
export function AuthMethodChoice({ google, googleHref, googleError, divider, email, onEmail }: Props) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                {/*
                 * A plain anchor, and it has to stay one. The flow starts by
                 * handing the browser over to Google's consent screen, so this is
                 * a full document navigation, not a request the app makes: an
                 * Inertia visit or an axios call would send an XHR to an origin
                 * that answers with no CORS headers, and the redirect would
                 * surface as an unexplained network failure instead.
                 */}
                <Button
                    asChild
                    variant="outline"
                    size="lg"
                    className="h-12 w-full gap-3 rounded-xl text-sm"
                >
                    <a href={googleHref}>
                        <GoogleIcon className="size-5" />
                        {google}
                    </a>
                </Button>

                {googleError ? (
                    <p role="alert" className="text-center text-xs text-destructive">
                        {googleError}
                    </p>
                ) : null}
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
