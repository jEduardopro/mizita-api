import { cn } from 'cn';
import { Lock } from 'lucide-react';
import { CONTROL_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    lockedReason: string;
    value: string;
};

export function LockedEmailField({ id, label, lockedReason, value }: Props) {
    const density = useFormDensity();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div className="relative">
                <Input
                    id={id}
                    type="email"
                    readOnly
                    aria-describedby={`${id}-locked`}
                    value={value}
                    className={cn('bg-muted pr-10 text-muted-foreground', CONTROL_DENSITY_CLASSES[density])}
                />

                <Lock
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <p id={`${id}-locked`} className="sr-only">
                {lockedReason}
            </p>
        </div>
    );
}
