import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = ComponentProps<'input'> & {
    id: string;
    label: string;
    /** The message Laravel sent back for this field, if any. */
    error?: string;
};

/**
 * A labelled input that renders the server's validation message underneath.
 *
 * The backend's FormRequest is the only authority on what is valid, so there is
 * no client-side schema here: native attributes are affordances, and the real
 * answer always arrives from the server.
 */
export function FormField({ id, label, error, ...props }: Props) {
    const errorId = `${id}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                aria-invalid={!! error}
                aria-describedby={error ? errorId : undefined}
                {...props}
            />
            {error ? (
                <p id={errorId} className="text-xs text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
