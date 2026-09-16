import { cn } from 'cn';
import { LoaderCircle } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

const STACKED_CELL = 'col-start-1 row-start-1 flex items-center justify-center gap-1.5';

type Props = Omit<ComponentProps<typeof Button>, 'children'> & {
    label: string;
    submittingLabel: string;
    isSubmitting: boolean;
};

export function SubmitButton({
    label,
    submittingLabel,
    isSubmitting,
    disabled,
    className,
    ...props
}: Props) {
    return (
        <Button
            type="submit"
            {...props}
            aria-busy={isSubmitting}
            disabled={disabled === true || isSubmitting}
            className={cn('inline-grid', className)}
        >
            <span className={cn(STACKED_CELL, isSubmitting && 'invisible')}>{label}</span>

            <span className={cn(STACKED_CELL, ! isSubmitting && 'invisible')}>
                <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                {submittingLabel}
            </span>
        </Button>
    );
}
