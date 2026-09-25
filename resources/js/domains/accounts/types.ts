import type {
    AuthenticationResponseJSON,
    PublicKeyCredentialCreationOptionsJSON,
    PublicKeyCredentialRequestOptionsJSON,
    RegistrationResponseJSON,
} from '@simplewebauthn/browser';

export type UpdatePasswordPayload = {
    current_password?: string;
    password: string;
    password_confirmation: string;
};

export type AccountDeletionBlocker = 'upcoming_appointments';

export type OwnedBusinessSummary = {
    id: string;
    name: string;
};

export type AccountDeletionPreview = {
    email: string;
    has_password: boolean;
    owned_business: OwnedBusinessSummary | null;
    upcoming_appointments_count: number;
    blocked_by: AccountDeletionBlocker | null;
    grace_period_ends_at: string;
};

export type DeleteAccountPayload = {
    password?: string;
    email?: string;
};

export type ReactivationBusiness = {
    name: string;
    closed_at: string;
    purge_scheduled_at: string;
    purged: boolean;
};

export type AccountReactivationStatus = {
    email: string;
    name: string;
    deletion_requested_at: string;
    grace_period_ends_at: string;
    business: ReactivationBusiness | null;
};

export type AccountReactivated = {
    two_factor_required: boolean;
};

export type TwoFactorStatus = 'disabled' | 'pending' | 'enabled';

export type Passkey = {
    id: string;
    name: string;
    authenticator: string | null;
    created_at: string;
    last_used_at: string | null;
};

export type SignInSecurity = {
    has_password: boolean;
    two_factor: TwoFactorStatus;
    passkeys: Passkey[];
};

export type ConfirmPasswordPayload = {
    password: string;
};

export type ConfirmTwoFactorPayload = {
    code: string;
};

export type TwoFactorQrCode = {
    svg: string;
    url: string;
};

export type PasskeyRegistrationOptions = PublicKeyCredentialCreationOptionsJSON;

export type PasskeyLoginOptions = PublicKeyCredentialRequestOptionsJSON;

export type RegisterPasskeyPayload = {
    name: string;
    credential: RegistrationResponseJSON;
};

export type RegisteredPasskey = {
    id: string;
    name: string;
};

export type PasskeySignInPayload = {
    credential: AuthenticationResponseJSON;
    remember: boolean;
};

export type PasskeySignIn = {
    redirect: string;
};

export const INCORRECT_ACCOUNT_PASSWORD_CODE = 'incorrect_account_password';

export const ACCOUNT_DELETION_EMAIL_MISMATCH_CODE = 'account_deletion_email_mismatch';

export const ACCOUNT_HAS_UPCOMING_APPOINTMENTS_CODE = 'account_has_upcoming_appointments';

export const ACCOUNT_REACTIVATION_NOT_PENDING_CODE = 'account_reactivation_not_pending';
