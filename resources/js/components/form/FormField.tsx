import { cn } from 'cn';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage, type HintTone } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = ComponentProps<'input'> & {
    id: string;
    label: string;
    /** The message Laravel sent back for this field, if any. */
    error?: string;
    /** A note under the field, pre-translated. An error supersedes it. */
    hint?: string;
    /** How the hint reads: neutral by default, or a verdict the server gave. */
    hintTone?: HintTone;
    /** Holds the message row's height, so a verdict never shifts the layout. */
    reserveMessageSpace?: boolean;
};

/**
 * A labelled input that renders the server's validation message underneath.
 *
 * The backend's FormRequest is the only authority on what is valid, so there is
 * no client-side schema here: native attributes are affordances, and the real
 * answer always arrives from the server.
 *
 * Which of the hint and the error is shown, and which id `aria-describedby`
 * points at, is decided by `fieldMessage` — the same rule `UnderlineField`
 * follows, kept in one place so the siblings cannot drift.
 */
export function FormField({
    id,
    label,
    error,
    hint,
    hintTone,
    reserveMessageSpace,
    className,
    ...props
}: Props) {
    const message = fieldMessage({ id, error, hint, hintTone });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                aria-invalid={!! error}
                aria-describedby={message?.id}
                {...props}
                // The input is 44px tall and set at 16px on every viewport: below
                // that, iOS zooms the page the moment the field takes focus and
                // leaves the person stranded mid-form.
                className={cn('h-11 text-base md:text-base', className)}
            />
            <FieldMessage message={message} reserveSpace={reserveMessageSpace} />
        </div>
    );
}
