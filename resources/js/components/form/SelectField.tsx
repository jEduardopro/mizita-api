import { cn } from 'cn';
import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';

export type SelectOption = {
    value: string;
    label: string;
};

type Props = Omit<ComponentProps<'select'>, 'children'> & {
    id: string;
    label: string;
    options: readonly SelectOption[];
    error?: string;
    hint?: string;
};

export function SelectField({ id, label, options, error, hint, className, ...props }: Props) {
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div className="relative">
                <select
                    {...props}
                    id={id}
                    aria-invalid={!! error}
                    aria-describedby={message?.id}
                    className={cn(
                        'h-11 w-full appearance-none rounded-lg border border-input bg-transparent py-1 pr-9 pl-2.5 text-base transition-colors outline-none',
                        'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                        'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
                        'dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40',
                        className,
                    )}
                >
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>

                <ChevronDown
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <FieldMessage message={message} />
        </div>
    );
}
