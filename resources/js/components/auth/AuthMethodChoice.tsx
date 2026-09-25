import type { ReactNode } from 'react';
import { GoogleIcon } from '@/components/auth/GoogleIcon';
import { Button } from '@/components/ui/button';

type Props = {
    google: string;
    googleHref: string;
    googleError?: string;
    passkey?: ReactNode;
    divider: string;
    email: string;
    onEmail: () => void;
};

export function AuthMethodChoice({ google, googleHref, googleError, passkey, divider, email, onEmail }: Props) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-3">
                <div className="grid gap-2">
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

                {passkey}
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
