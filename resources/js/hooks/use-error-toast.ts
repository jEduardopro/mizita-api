import { useCallback, useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';

type ErrorToast = {
    /** Raises a failure, replacing the one this caller raised before it. */
    show(message: string): void;
    dismiss(): void;
};

/**
 * One caller's hold on the notification region, for a failure that belongs to a
 * whole action rather than to a single field. The message is handed in already
 * written, because the server's sentence is the only accurate account of why a
 * request was refused.
 */
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
                // Never auto-closes: on a flat rejection no field turns red, so
                // this sentence is the only explanation there is.
                duration: Infinity,
                closeButton: true,
            });
        },
        [dismiss],
    );

    useEffect(() => dismiss, [dismiss]);

    return useMemo(() => ({ show, dismiss }), [show, dismiss]);
}
