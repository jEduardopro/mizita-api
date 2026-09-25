import { useCallback } from 'react';
import { isPasskeyCancellation, supportsPasskeys } from '@/lib/webauthn';
import { useSignInWithPasskey, type PasskeySignInRequest } from '../queries';

type PasskeySignInFlow = {
    isSupported: boolean;
    isSigningIn: boolean;
    hasFailed: boolean;
    signIn: () => void;
};

export function usePasskeySignIn({ remember }: PasskeySignInRequest): PasskeySignInFlow {
    const { mutate, isPending, isSuccess, isError, error } = useSignInWithPasskey();

    const signIn = useCallback(() => {
        mutate(
            { remember },
            { onSuccess: ({ redirect }) => window.location.assign(redirect) },
        );
    }, [mutate, remember]);

    return {
        isSupported: supportsPasskeys(),
        isSigningIn: isPending || isSuccess,
        hasFailed: isError && ! isPasskeyCancellation(error),
        signIn,
    };
}
