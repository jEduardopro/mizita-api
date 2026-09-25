import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { createPasskeyCredential, requestPasskeyAssertion } from '@/lib/webauthn';
import {
    confirmPassword,
    confirmTwoFactor,
    deleteAccount,
    deletePasskey,
    disableTwoFactor,
    dismissAccountReactivation,
    enableTwoFactor,
    getAccountDeletionPreview,
    getAccountReactivation,
    getPasskeyLoginOptions,
    getPasskeyRegistrationOptions,
    getPasswordConfirmationStatus,
    getSignInSecurity,
    getTwoFactorQrCode,
    getTwoFactorRecoveryCodes,
    getTwoFactorSecretKey,
    reactivateAccount,
    regenerateTwoFactorRecoveryCodes,
    registerPasskey,
    signInWithPasskey,
    updatePassword,
} from './api';
import type { PasskeySignIn, RegisteredPasskey } from './types';

export const accountKeys = {
    all: ['accounts'] as const,
    deletionPreview: () => [...accountKeys.all, 'deletion-preview'] as const,
    reactivation: () => [...accountKeys.all, 'reactivation'] as const,
    security: () => [...accountKeys.all, 'security'] as const,
    passwordConfirmation: () => [...accountKeys.all, 'password-confirmation'] as const,
    twoFactor: () => [...accountKeys.all, 'two-factor'] as const,
    twoFactorQrCode: () => [...accountKeys.twoFactor(), 'qr-code'] as const,
    twoFactorSecretKey: () => [...accountKeys.twoFactor(), 'secret-key'] as const,
    twoFactorRecoveryCodes: () => [...accountKeys.twoFactor(), 'recovery-codes'] as const,
};

const NEVER_CACHED = { gcTime: 0, staleTime: Infinity } as const;

type OnDemandOptions = {
    enabled: boolean;
};

export type PasskeySignInRequest = {
    remember: boolean;
};

export function useUpdatePassword() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: updatePassword,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: accountKeys.security() }),
    });
}

type DeletionPreviewOptions = {
    enabled: boolean;
};

export function useAccountDeletionPreview({ enabled }: DeletionPreviewOptions) {
    return useQuery({
        queryKey: accountKeys.deletionPreview(),
        queryFn: ({ signal }) => getAccountDeletionPreview(signal),
        enabled,
        staleTime: 0,
    });
}

export function useDeleteAccount() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deleteAccount,
        onSuccess: () => queryClient.clear(),
    });
}

export function useAccountReactivation() {
    return useQuery({
        queryKey: accountKeys.reactivation(),
        queryFn: ({ signal }) => getAccountReactivation(signal),
    });
}

export function useReactivateAccount() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: reactivateAccount,
        onSuccess: () => queryClient.clear(),
    });
}

export function useDismissAccountReactivation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: dismissAccountReactivation,
        onSuccess: () => queryClient.removeQueries({ queryKey: accountKeys.reactivation() }),
    });
}

export function useSignInSecurity() {
    return useQuery({
        queryKey: accountKeys.security(),
        queryFn: ({ signal }) => getSignInSecurity(signal),
    });
}

export function usePasswordConfirmationCheck(): () => Promise<boolean> {
    const queryClient = useQueryClient();

    return useCallback(
        () =>
            queryClient.fetchQuery({
                queryKey: accountKeys.passwordConfirmation(),
                queryFn: ({ signal }) => getPasswordConfirmationStatus(signal),
                gcTime: 0,
                staleTime: 0,
            }),
        [queryClient],
    );
}

export function useConfirmPassword() {
    return useMutation({ mutationFn: confirmPassword });
}

export function useEnableTwoFactor() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: enableTwoFactor,
        onSuccess: () =>
            Promise.all([
                queryClient.invalidateQueries({ queryKey: accountKeys.security() }),
                queryClient.invalidateQueries({ queryKey: accountKeys.twoFactor() }),
            ]),
    });
}

export function useConfirmTwoFactor() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: confirmTwoFactor,
        onSuccess: () =>
            Promise.all([
                queryClient.invalidateQueries({ queryKey: accountKeys.security() }),
                queryClient.invalidateQueries({ queryKey: accountKeys.twoFactorRecoveryCodes() }),
            ]),
    });
}

export function useDisableTwoFactor() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: disableTwoFactor,
        onSuccess: () => {
            queryClient.removeQueries({ queryKey: accountKeys.twoFactor() });

            return queryClient.invalidateQueries({ queryKey: accountKeys.security() });
        },
    });
}

export function useTwoFactorQrCode({ enabled }: OnDemandOptions) {
    return useQuery({
        queryKey: accountKeys.twoFactorQrCode(),
        queryFn: ({ signal }) => getTwoFactorQrCode(signal),
        enabled,
        ...NEVER_CACHED,
    });
}

export function useTwoFactorSecretKey({ enabled }: OnDemandOptions) {
    return useQuery({
        queryKey: accountKeys.twoFactorSecretKey(),
        queryFn: ({ signal }) => getTwoFactorSecretKey(signal),
        enabled,
        ...NEVER_CACHED,
    });
}

export function useTwoFactorRecoveryCodes({ enabled }: OnDemandOptions) {
    return useQuery({
        queryKey: accountKeys.twoFactorRecoveryCodes(),
        queryFn: ({ signal }) => getTwoFactorRecoveryCodes(signal),
        enabled,
        ...NEVER_CACHED,
    });
}

export function useRegenerateRecoveryCodes() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: regenerateTwoFactorRecoveryCodes,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: accountKeys.twoFactorRecoveryCodes() }),
    });
}

async function registerPasskeyOnThisDevice(name: string): Promise<RegisteredPasskey> {
    const options = await getPasskeyRegistrationOptions();
    const credential = await createPasskeyCredential(options);

    return registerPasskey({ name, credential });
}

export function useRegisterPasskey() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: registerPasskeyOnThisDevice,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: accountKeys.security() }),
    });
}

export function useDeletePasskey() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deletePasskey,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: accountKeys.security() }),
    });
}

async function signInWithPasskeyOnThisDevice({ remember }: PasskeySignInRequest): Promise<PasskeySignIn> {
    const options = await getPasskeyLoginOptions();
    const credential = await requestPasskeyAssertion(options);

    return signInWithPasskey({ credential, remember });
}

export function useSignInWithPasskey() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: signInWithPasskeyOnThisDevice,
        onSuccess: () => queryClient.clear(),
    });
}
