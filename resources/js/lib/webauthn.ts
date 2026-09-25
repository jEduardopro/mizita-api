import {
    browserSupportsWebAuthn,
    startAuthentication,
    startRegistration,
    WebAuthnError,
    type AuthenticationResponseJSON,
    type PublicKeyCredentialCreationOptionsJSON,
    type PublicKeyCredentialRequestOptionsJSON,
    type RegistrationResponseJSON,
} from '@simplewebauthn/browser';

const CANCELLATION_ERROR_NAMES: readonly string[] = ['NotAllowedError', 'AbortError'];

export function supportsPasskeys(): boolean {
    return browserSupportsWebAuthn();
}

export function createPasskeyCredential(
    options: PublicKeyCredentialCreationOptionsJSON,
): Promise<RegistrationResponseJSON> {
    return startRegistration({ optionsJSON: options });
}

export function requestPasskeyAssertion(
    options: PublicKeyCredentialRequestOptionsJSON,
): Promise<AuthenticationResponseJSON> {
    return startAuthentication({ optionsJSON: options });
}

export function isPasskeyCancellation(error: unknown): boolean {
    if (error instanceof WebAuthnError && error.code === 'ERROR_CEREMONY_ABORTED') {
        return true;
    }

    return error instanceof Error && CANCELLATION_ERROR_NAMES.includes(error.name);
}

export function isPasskeyAlreadyRegistered(error: unknown): boolean {
    return error instanceof WebAuthnError && error.code === 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED';
}
