import { GoogleIcon } from '@/components/auth/GoogleIcon';
import { Button } from '@/components/ui/button';

type Props = {
    google: string;
    /** Where the Google flow starts. A server route, not an API endpoint. */
    googleHref: string;
    /** What to say when a Google attempt came back without a session. */
    googleError?: string;
    divider: string;
    email: string;
    onEmail: () => void;
};

export function AuthMethodChoice({ google, googleHref, googleError, divider, email, onEmail }: Props) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                {/* A plain anchor, and it has to stay one: the flow hands the
                    browser to Google's consent screen, so it is a full document
                    navigation. An Inertia visit or an axios call would send an
                    XHR to an origin that answers with no CORS headers. */}
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
