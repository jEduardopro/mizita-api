import { cn } from 'cn';

/**
 * How a hint reads. A hint is usually neutral, but a field that asks the server a
 * question mid-form — is this web address free? — comes back with a verdict, and
 * a verdict has a tone.
 */
export type HintTone = 'muted' | 'positive' | 'critical';

/**
 * `positive` is the one green in the product, and it is a token of its own
 * rather than the brand accent: the accent means "this is the action", and a
 * verdict is not something to press. `critical` borrows `--destructive` for the
 * same reason in reverse — a rejected value and a destructive action are the
 * same alarm.
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
    /** The id of the input this message belongs to. */
    id: string;
    /** The message Laravel sent back for this field, if any. */
    error?: string;
    /** A note worth reading before submitting, e.g. the password minimum. */
    hint?: string;
    hintTone?: HintTone;
};

/**
 * The single message a field shows, chosen once so every field chooses the same
 * way.
 *
 * One message per field. An error supersedes the hint rather than stacking under
 * it: the hint says what would be acceptable, and once the server has said what
 * was wrong, repeating the rule underneath is noise at the exact moment the field
 * is asking to be read. `UnderlineField` states the same rule inline, and this is
 * where its siblings read it from so the two cannot drift.
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
     * Holds the row's height while there is nothing to say. A field that asks the
     * server a question gains and loses its verdict as someone types, and the
     * button underneath must not move while their thumb is over it.
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
