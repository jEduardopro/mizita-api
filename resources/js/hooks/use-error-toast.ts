import { useCallback, useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';

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

            raised.current = toast.error(message, {
                duration: Infinity,
                closeButton: true,
            });
        },
        [dismiss],
    );

    useEffect(() => dismiss, [dismiss]);

    return useMemo(() => ({ show, dismiss }), [show, dismiss]);
}
