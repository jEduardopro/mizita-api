import { api } from '@/lib/api';
import type {
    AccountDeletionPreview,
    AccountReactivated,
    AccountReactivationStatus,
    ConfirmPasswordPayload,
    ConfirmTwoFactorPayload,
    DeleteAccountPayload,
    PasskeyLoginOptions,
    PasskeyRegistrationOptions,
    PasskeySignIn,
    PasskeySignInPayload,
    RegisteredPasskey,
    RegisterPasskeyPayload,
    SignInSecurity,
    TwoFactorQrCode,
    UpdatePasswordPayload,
} from './types';

const WEB_ROOT = '/';

export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
    await api.put('/user/password', payload, { baseURL: WEB_ROOT });
}

export async function getAccountDeletionPreview(signal?: AbortSignal): Promise<AccountDeletionPreview> {
    const { data } = await api.get<{ data: AccountDeletionPreview }>('/me/account/deletion', { signal });

    return data.data;
}

export async function deleteAccount(payload: DeleteAccountPayload): Promise<void> {
    await api.delete('/me/account', { data: payload });
}

export async function getAccountReactivation(signal?: AbortSignal): Promise<AccountReactivationStatus> {
    const { data } = await api.get<{ data: AccountReactivationStatus }>('/account-reactivation', { signal });

    return data.data;
}

export async function reactivateAccount(): Promise<AccountReactivated> {
    const { data } = await api.post<{ data: AccountReactivated }>('/account-reactivation');

    return data.data;
}

export async function dismissAccountReactivation(): Promise<void> {
    await api.delete('/account-reactivation');
}

export async function getSignInSecurity(signal?: AbortSignal): Promise<SignInSecurity> {
    const { data } = await api.get<{ data: SignInSecurity }>('/me/security', { signal });

    return data.data;
}

export async function getPasswordConfirmationStatus(signal?: AbortSignal): Promise<boolean> {
    const { data } = await api.get<{ confirmed: boolean }>('/user/confirmed-password-status', {
        baseURL: WEB_ROOT,
        signal,
    });

    return data.confirmed;
}

export async function confirmPassword(payload: ConfirmPasswordPayload): Promise<void> {
    await api.post('/user/confirm-password', payload, { baseURL: WEB_ROOT });
}

export async function enableTwoFactor(): Promise<void> {
    await api.post('/user/two-factor-authentication', undefined, { baseURL: WEB_ROOT });
}

export async function confirmTwoFactor(payload: ConfirmTwoFactorPayload): Promise<void> {
    await api.post('/user/confirmed-two-factor-authentication', payload, { baseURL: WEB_ROOT });
}

export async function disableTwoFactor(): Promise<void> {
    await api.delete('/user/two-factor-authentication', { baseURL: WEB_ROOT });
}

export async function getTwoFactorQrCode(signal?: AbortSignal): Promise<TwoFactorQrCode> {
    const { data } = await api.get<TwoFactorQrCode>('/user/two-factor-qr-code', { baseURL: WEB_ROOT, signal });

    return data;
}

export async function getTwoFactorSecretKey(signal?: AbortSignal): Promise<string> {
    const { data } = await api.get<{ secretKey: string }>('/user/two-factor-secret-key', {
        baseURL: WEB_ROOT,
        signal,
    });

    return data.secretKey;
}

export async function getTwoFactorRecoveryCodes(signal?: AbortSignal): Promise<string[]> {
    const { data } = await api.get<string[]>('/user/two-factor-recovery-codes', { baseURL: WEB_ROOT, signal });

    return data;
}

export async function regenerateTwoFactorRecoveryCodes(): Promise<void> {
    await api.post('/user/two-factor-recovery-codes', undefined, { baseURL: WEB_ROOT });
}

export async function getPasskeyRegistrationOptions(): Promise<PasskeyRegistrationOptions> {
    const { data } = await api.get<{ options: PasskeyRegistrationOptions }>('/user/passkeys/options', {
        baseURL: WEB_ROOT,
    });

    return data.options;
}

export async function registerPasskey(payload: RegisterPasskeyPayload): Promise<RegisteredPasskey> {
    const { data } = await api.post<{ data: RegisteredPasskey }>('/user/passkeys', payload, { baseURL: WEB_ROOT });

    return data.data;
}

export async function deletePasskey(id: string): Promise<void> {
    await api.delete(`/user/passkeys/${id}`, { baseURL: WEB_ROOT });
}

export async function getPasskeyLoginOptions(): Promise<PasskeyLoginOptions> {
    const { data } = await api.get<{ options: PasskeyLoginOptions }>('/passkeys/login/options', {
        baseURL: WEB_ROOT,
    });

    return data.options;
}

export async function signInWithPasskey(payload: PasskeySignInPayload): Promise<PasskeySignIn> {
    const { data } = await api.post<PasskeySignIn>('/passkeys/login', payload, { baseURL: WEB_ROOT });

    return data;
}
