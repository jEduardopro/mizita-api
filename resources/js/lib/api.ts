import axios from 'axios';
import { currentLocale } from '@/lib/i18n';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        // Makes Laravel treat the request as AJAX, so it returns JSON errors
        // instead of a redirect to the login page.
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    // Since axios 1.6 the XSRF header has to be opted into explicitly. Axios
    // reads the XSRF-TOKEN cookie Laravel sets and mirrors it into the
    // X-XSRF-TOKEN header, which is what Sanctum validates on stateful calls.
    withXSRFToken: true,
});

// Set per request rather than once at creation: the visitor can change language
// mid-session, and a baked-in header would keep announcing the one they started
// with.
api.interceptors.request.use((config) => {
    config.headers.set('X-Locale', currentLocale());

    return config;
});
