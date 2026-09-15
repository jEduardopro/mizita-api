import { cn } from 'cn';

export type HintTone = 'muted' | 'positive' | 'critical';

const tones: Record<HintTone, string> = {
    muted: 'text-muted-foreground',
    positive: 'font-medium text-success',
    critical: 'text-destructive',
};

type FieldMessageState = {
    id: string;
    text: string;
    tone: HintTone;
};

type Field = {
    id: string;
    error?: string;
    hint?: string;
    hintTone?: HintTone;
};

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
    reserveSpace?: boolean;
};

export function FieldMessage({ message, reserveSpace = false }: Props) {
    if (! message) {
        return reserveSpace ? <span aria-hidden="true" className="block min-h-4" /> : null;
    }

    return (
        <span id={message.id} className={cn('block min-h-4 text-xs', tones[message.tone])}>
            {message.text}
        </span>
    );
}
