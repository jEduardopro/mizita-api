import { cn } from 'cn';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

export function FilterChipApply({ className, type = 'button', ...props }: ComponentProps<typeof Button>) {
    return (
        <div className="shrink-0 border-t border-border p-3">
            <Button type={type} className={cn('h-11 w-full rounded-full px-4 text-sm md:h-9', className)} {...props} />
        </div>
    );
}
