import axios from 'axios';
import { responseBodyFrom, warningsFrom } from '@/lib/http';
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

function rejectWithWarnings(error: unknown): Promise<never> {
    raiseWarningToasts(warningsFrom(responseBodyFrom(error)));

    return Promise.reject(error);
}

api.interceptors.response.use((response) => {
    raiseWarningToasts(warningsFrom(response.data));

    return response;
}, rejectWithWarnings);
