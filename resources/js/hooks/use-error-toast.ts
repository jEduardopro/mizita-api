import { useCallback, useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';
import { raiseErrorToast } from '@/lib/toast';

type ErrorToast = {
    show(message: string): void;
    dismiss(): void;
};

export function useErrorToast(): ErrorToast {
    const raised = useRef<string | number | null>(null);

    const dismiss = useCallback(() => {
        if (raised.current === null) {
            return;
        }

        toast.dismiss(raised.current);
        raised.current = null;
    }, []);

    const show = useCallback(
        (message: string) => {
            dismiss();

            raised.current = raiseErrorToast(message);
        },
        [dismiss],
    );

    useEffect(() => dismiss, [dismiss]);

    return useMemo(() => ({ show, dismiss }), [show, dismiss]);
}
