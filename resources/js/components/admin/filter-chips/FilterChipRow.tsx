import { cn } from 'cn';
import type { ComponentProps } from 'react';

export function FilterChipRow({ className, ...props }: ComponentProps<'div'>) {
    return <div role="group" className={cn('flex min-w-0 flex-wrap items-center gap-2', className)} {...props} />;
}
