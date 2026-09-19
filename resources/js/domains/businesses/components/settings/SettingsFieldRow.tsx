import type { ReactNode } from 'react';
import { FieldMessage, type FieldMessageState } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';

export function settingsFieldLabelId(htmlFor: string): string {
    return `${htmlFor}-label`;
}

type Props = {
    htmlFor: string;
    label: string;
    helper: string;
    labelAdornment?: ReactNode;
    message: FieldMessageState | null;
    children: ReactNode;
};

export function SettingsFieldRow({
    htmlFor,
    label,
    helper,
    labelAdornment,
    message,
    children,
}: Props) {
    return (
        <div className="grid gap-3 sm:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] sm:items-start sm:gap-6">
            <div className="grid gap-1">
                <div className="flex items-center gap-1.5">
                    <Label id={settingsFieldLabelId(htmlFor)} htmlFor={htmlFor}>
                        {label}
                    </Label>

                    {labelAdornment}
                </div>

                <p className="text-xs text-pretty text-muted-foreground">{helper}</p>
            </div>

            <div className="grid gap-2">
                <div className="min-w-0">{children}</div>

                <FieldMessage message={message} />
            </div>
        </div>
    );
}
