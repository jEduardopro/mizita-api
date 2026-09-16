import { cn } from 'cn';
import { ListChecks } from 'lucide-react';
import type { ServiceColor } from '../types';
import { serviceColorClasses } from './service-color';

type Props = {
    color: ServiceColor;
    imageUrl: string | null;
    className?: string;
};

export function ServiceColorTile({ color, imageUrl, className }: Props) {
    const classes = serviceColorClasses[color];

    if (imageUrl !== null) {
        return (
            <img
                src={imageUrl}
                alt=""
                className={cn('size-11 shrink-0 rounded-xl object-cover', className)}
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-11 shrink-0 items-center justify-center rounded-xl',
                classes.tile,
                className,
            )}
        >
            <ListChecks className={cn('size-5', classes.icon)} />
        </span>
    );
}
