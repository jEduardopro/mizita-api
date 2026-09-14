import { useCallback, useEffect, useMemo, useRef } from 'react';
import { dismissToasts, raiseWarningToasts, type ToastId } from '@/lib/toast';
import type { ApiWarning } from '@/types/api';

type WarningToast = {
    show(warnings: ApiWarning[]): void;
    dismiss(): void;
};

export function useWarningToast(): WarningToast {
    const raised = useRef<ToastId[]>([]);

    const dismiss = useCallback(() => {
        dismissToasts(raised.current);
        raised.current = [];
    }, []);

    const show = useCallback((warnings: ApiWarning[]) => {
        raised.current = [...raised.current, ...raiseWarningToasts(warnings)];
    }, []);

    useEffect(() => dismiss, [dismiss]);

    return useMemo(() => ({ show, dismiss }), [show, dismiss]);
}
