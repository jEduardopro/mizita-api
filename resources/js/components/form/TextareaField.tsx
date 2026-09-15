import { cn } from 'cn';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage, type HintTone } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = ComponentProps<'textarea'> & {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    hintTone?: HintTone;
};

export function TextareaField({ id, label, error, hint, hintTone, className, ...props }: Props) {
    const message = fieldMessage({ id, error, hint, hintTone });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <Textarea
                id={id}
                aria-invalid={!! error}
                aria-describedby={message?.id}
                {...props}
                className={cn('min-h-28 text-base md:text-base', className)}
            />

            <FieldMessage message={message} />
        </div>
    );
}
