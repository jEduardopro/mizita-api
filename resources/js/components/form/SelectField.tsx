import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { SelectControl, type SelectOption } from '@/components/form/SelectControl';
import { Label } from '@/components/ui/label';

export type { SelectOption };

type Props = Omit<ComponentProps<'select'>, 'children'> & {
    id: string;
    label: string;
    options: readonly SelectOption[];
    error?: string;
    hint?: string;
};

export function SelectField({ id, label, options, error, hint, ...props }: Props) {
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <SelectControl
                {...props}
                id={id}
                options={options}
                aria-invalid={!! error}
                aria-describedby={message?.id}
            />

            <FieldMessage message={message} />
        </div>
    );
}
