import { cn } from 'cn';
import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = Omit<ComponentProps<'input'>, 'placeholder'> & {
    id: string;
    label: string;
    hint?: string;
    /** The message Laravel sent back for this field, if any. */
    error?: string;
    /** Passing the pair rather than a boolean keeps the toggle from ever
     * shipping without an accessible name. */
    reveal?: { show: string; hide: string };
};

/**
 * The floating label rests on the line while the field is empty and rises once
 * it has a value or focus, decided by `:placeholder-shown` through Tailwind's
 * `peer`. That costs no state and gets browser autofill right for free: the
 * label lifts because the field genuinely has a value, not because React was
 * told about it.
 *
 * The two states are one arbitrary variant rather than a
 * `peer-placeholder-shown` / `peer-focus` pair, which would set the same
 * properties from two variants and leave the winner to Tailwind's ordering.
 *
 * Focus is a 2px bar rather than a ring: a 1px colour change is too quiet to be
 * a keyboard focus indicator on a borderless field, and `components/ui/input.tsx`
 * is generated, so its ring is switched off here rather than edited there.
 */
export function UnderlineField({ id, label, hint, error, reveal, className, ...props }: Props) {
    const [revealed, setRevealed] = useState(false);

    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    // One message per field, the same rule `FieldMessage` states for its siblings.
    const showHint = hint !== undefined && ! error;

    const describedBy = showHint ? hintId : error ? errorId : undefined;

    const RevealIcon = revealed ? EyeOff : Eye;

    return (
        <div className="grid gap-1.5">
            <div className="relative">
                <Input
                    id={id}
                    // The mechanism behind the floating label, not copy: a single
                    // space is what makes `:placeholder-shown` mean "empty".
                    placeholder=" "
                    aria-invalid={!! error}
                    aria-describedby={describedBy}
                    {...props}
                    type={reveal ? (revealed ? 'text' : 'password') : props.type}
                    className={cn(
                        'peer h-14 rounded-none border-0 border-b bg-transparent px-0 pt-6 pb-1.5 text-base placeholder:text-transparent focus-visible:ring-0 aria-invalid:ring-0 md:text-base dark:bg-transparent',
                        reveal ? 'pr-11' : undefined,
                        className,
                    )}
                />

                <Label
                    htmlFor={id}
                    className={cn(
                        'pointer-events-none absolute top-1.5 left-0 origin-left text-xs font-normal text-muted-foreground',
                        'peer-[:placeholder-shown:not(:focus)]:top-1/2 peer-[:placeholder-shown:not(:focus)]:-translate-y-1/2 peer-[:placeholder-shown:not(:focus)]:text-base',
                        'peer-focus:text-foreground peer-aria-invalid:text-destructive',
                        'motion-safe:transition-all motion-safe:duration-200',
                    )}
                >
                    {label}
                </Label>

                {/* Inherits the error colour, so a field being corrected stays
                    red while focused instead of flipping to brand mid-fix. */}
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 origin-left scale-x-0 bg-primary transition-transform duration-200 peer-focus-visible:scale-x-100 peer-aria-invalid:bg-destructive motion-reduce:transition-none"
                />

                {reveal ? (
                    <button
                        type="button"
                        onClick={() => setRevealed((shown) => ! shown)}
                        aria-label={revealed ? reveal.hide : reveal.show}
                        aria-pressed={revealed}
                        className="absolute right-0 bottom-0 flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <RevealIcon className="size-[1.125rem]" />
                    </button>
                ) : null}
            </div>

            {showHint ? (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            ) : null}

            {error ? (
                <p id={errorId} className="text-xs text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
