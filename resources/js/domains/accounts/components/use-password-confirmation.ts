import { useCallback, useMemo, useRef, useState } from 'react';
import { isPasswordConfirmationRequiredError } from '@/lib/http';
import { usePasswordConfirmationCheck } from '../queries';

export type PasswordConfirmationOutcome<T> = { status: 'completed'; value: T } | { status: 'cancelled' };

export type PasswordConfirmationDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirmed: () => void;
};

export type PasswordConfirmation = {
    confirmThen: <T>(action: () => Promise<T>) => Promise<PasswordConfirmationOutcome<T>>;
    isChecking: boolean;
    dialog: PasswordConfirmationDialogProps;
};

type PendingAction = {
    resume: () => void;
    cancel: () => void;
};

type Settlement<T> = {
    resolve: (outcome: PasswordConfirmationOutcome<T>) => void;
    reject: (error: unknown) => void;
};

function guardedAction<T>(
    action: () => Promise<T>,
    requestPassword: (pending: PendingAction) => void,
    { resolve, reject }: Settlement<T>,
): PendingAction {
    let hasAskedAgain = false;

    const pending: PendingAction = {
        resume: () => void attempt(),
        cancel: () => resolve({ status: 'cancelled' }),
    };

    async function attempt(): Promise<void> {
        try {
            resolve({ status: 'completed', value: await action() });
        } catch (error) {
            if (hasAskedAgain || ! isPasswordConfirmationRequiredError(error)) {
                reject(error);

                return;
            }

            hasAskedAgain = true;
            requestPassword(pending);
        }
    }

    return pending;
}

export function usePasswordConfirmation(): PasswordConfirmation {
    const isPasswordConfirmed = usePasswordConfirmationCheck();
    const pendingAction = useRef<PendingAction | null>(null);
    const [isDialogOpen, setDialogOpen] = useState(false);
    const [isChecking, setChecking] = useState(false);

    const requestPassword = useCallback((pending: PendingAction) => {
        if (pendingAction.current !== pending) {
            pendingAction.current?.cancel();
        }

        pendingAction.current = pending;
        setDialogOpen(true);
    }, []);

    const confirmThen = useCallback(
        <T>(action: () => Promise<T>) =>
            new Promise<PasswordConfirmationOutcome<T>>((resolve, reject) => {
                const pending = guardedAction(action, requestPassword, { resolve, reject });

                setChecking(true);

                isPasswordConfirmed()
                    .then(
                        (confirmed) => (confirmed ? pending.resume() : requestPassword(pending)),
                        () => pending.resume(),
                    )
                    .finally(() => setChecking(false));
            }),
        [isPasswordConfirmed, requestPassword],
    );

    const onOpenChange = useCallback((open: boolean) => {
        if (open) {
            return;
        }

        setDialogOpen(false);
        pendingAction.current?.cancel();
        pendingAction.current = null;
    }, []);

    const onConfirmed = useCallback(() => {
        const pending = pendingAction.current;

        pendingAction.current = null;
        setDialogOpen(false);
        pending?.resume();
    }, []);

    return useMemo(
        () => ({
            confirmThen,
            isChecking,
            dialog: { open: isDialogOpen, onOpenChange, onConfirmed },
        }),
        [confirmThen, isChecking, isDialogOpen, onOpenChange, onConfirmed],
    );
}
