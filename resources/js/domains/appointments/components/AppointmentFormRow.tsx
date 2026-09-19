import { cn } from 'cn';
import type { ReactNode } from 'react';
import { FieldMessage, type FieldMessageState } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';

export const APPOINTMENT_CONTROL_HEIGHT = 'min-h-11 md:min-h-9';

export function appointmentLabelId(fieldId: string): string {
    return `${fieldId}-label`;
}

type Props = {
    icon: ReactNode;
    label: string;
    htmlFor: string;
    message: FieldMessageState | null;
    children: ReactNode;
};

export function AppointmentFormRow({ icon, label, htmlFor, message, children }: Props) {
    return (
        <div className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2">
            <Label id={appointmentLabelId(htmlFor)} htmlFor={htmlFor} className="col-start-2 row-start-1">
                {label}
            </Label>

            <span
                aria-hidden="true"
                className={cn(
                    'col-start-1 row-start-2 flex w-6 items-center justify-center self-start text-muted-foreground [&>svg]:size-4',
                    APPOINTMENT_CONTROL_HEIGHT,
                )}
            >
                {icon}
            </span>

            <div className="col-start-2 row-start-2 min-w-0">{children}</div>

            {message === null ? null : (
                <div className="col-start-2 row-start-3">
                    <FieldMessage message={message} />
                </div>
            )}
        </div>
    );
}
