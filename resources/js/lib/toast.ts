import { toast } from 'sonner';
import type { ApiWarning } from '@/types/api';

const WARNING_DURATION = 10_000;

export type ToastId = string | number;

export function raiseWarningToasts(warnings: ApiWarning[]): ToastId[] {
    return warnings.map((warning) =>
        toast.warning(warning.message, {
            id: warning.code,
            duration: WARNING_DURATION,
        }),
    );
}

export function dismissToasts(ids: ToastId[]): void {
    for (const id of ids) {
        toast.dismiss(id);
    }
}
