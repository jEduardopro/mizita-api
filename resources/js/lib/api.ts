import { router } from '@inertiajs/react';
import axios from 'axios';
import { isPasswordChangeRequiredError, responseBodyFrom, warningsFrom } from '@/lib/http';
import { currentLocale } from '@/lib/i18n';
import { raiseWarningToasts } from '@/lib/toast';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    // Since axios 1.6 the XSRF header has to be opted into explicitly: axios
    // mirrors Laravel's XSRF-TOKEN cookie into the header Sanctum validates.
    withXSRFToken: true,
});

api.interceptors.request.use((config) => {
    config.headers.set('X-Locale', currentLocale());

    return config;
});

const PASSWORD_CHANGE_URL = '/password/change';

function redirectToPasswordChange(): void {
    if (window.location.pathname === PASSWORD_CHANGE_URL) {
        return;
    }

    router.visit(PASSWORD_CHANGE_URL, { replace: true });
}

function rejectFailedResponse(error: unknown): Promise<never> {
    if (isPasswordChangeRequiredError(error)) {
        redirectToPasswordChange();
    }

    raiseWarningToasts(warningsFrom(responseBodyFrom(error)));

    return Promise.reject(error);
}

api.interceptors.response.use((response) => {
    raiseWarningToasts(warningsFrom(response.data));

    return response;
}, rejectFailedResponse);
