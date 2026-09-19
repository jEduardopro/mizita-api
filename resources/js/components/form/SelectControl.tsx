import { cn } from 'cn';
import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { CONTROL_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';

export type SelectOption = {
    value: string;
    label: string;
};

type Props = Omit<ComponentProps<'select'>, 'children'> & {
    options: readonly SelectOption[];
};

export function SelectControl({ options, className, ...props }: Props) {
    const density = useFormDensity();

    return (
        <div className="relative">
            <select
                {...props}
                className={cn(
                    'w-full appearance-none rounded-lg border border-input bg-transparent py-1 pr-9 pl-2.5 transition-colors outline-none',
                    CONTROL_DENSITY_CLASSES[density],
                    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                    'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
                    'disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground',
                    'dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 dark:disabled:bg-input/80',
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
    );
}
