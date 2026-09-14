import { cn } from 'cn';

export type HintTone = 'muted' | 'positive' | 'critical';

/**
 * `positive` is a token of its own rather than the brand accent: the accent
 * means "this is the action", and a verdict is not something to press.
 */
const tones: Record<HintTone, string> = {
    muted: 'text-muted-foreground',
    positive: 'font-medium text-success',
    critical: 'text-destructive',
};

type FieldMessageState = {
    /** The id the field points `aria-describedby` at. */
    id: string;
    text: string;
    tone: HintTone;
};

type Field = {
    id: string;
    /** The message Laravel sent back for this field, if any. */
    error?: string;
    hint?: string;
    hintTone?: HintTone;
};

/**
 * One message per field: an error supersedes the hint rather than stacking under
 * it, because repeating the rule is noise once the server has said what was
 * wrong. Every field reads the rule from here so none of them can drift.
 */
export function fieldMessage({ id, error, hint, hintTone = 'muted' }: Field): FieldMessageState | null {
    if (error) {
        return { id: `${id}-error`, text: error, tone: 'critical' };
    }

    if (hint !== undefined) {
        return { id: `${id}-hint`, text: hint, tone: hintTone };
    }

    return null;
}

type Props = {
    message: FieldMessageState | null;
    /**
     * Holds the row's height while there is nothing to say, so the button
     * underneath does not move while a thumb is over it.
     */
    reserveSpace?: boolean;
};

export function FieldMessage({ message, reserveSpace = false }: Props) {
    if (! message) {
        return reserveSpace ? <p aria-hidden="true" className="min-h-4" /> : null;
    }

    return (
        <p id={message.id} className={cn('min-h-4 text-xs', tones[message.tone])}>
            {message.text}
        </p>
    );
}
