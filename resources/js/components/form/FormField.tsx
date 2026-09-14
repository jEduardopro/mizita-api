import { cn } from 'cn';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage, type HintTone } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = ComponentProps<'input'> & {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    hintTone?: HintTone;
    reserveMessageSpace?: boolean;
};

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
                className={cn('h-11 text-base md:text-base', className)}
            />
            <FieldMessage message={message} reserveSpace={reserveMessageSpace} />
        </div>
    );
}
