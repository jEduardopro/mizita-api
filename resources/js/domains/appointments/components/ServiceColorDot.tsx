import { cn } from 'cn';
import { serviceColorClasses, type ServiceColor } from '@/lib/service-color';

const UNSELECTED_DOT = 'bg-muted-foreground/40';

type Props = {
    color: ServiceColor | null;
};

export function ServiceColorDot({ color }: Props) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'size-3 shrink-0 rounded-full',
                color === null ? UNSELECTED_DOT : serviceColorClasses[color].bar,
            )}
        />
    );
}
