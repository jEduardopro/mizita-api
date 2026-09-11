import axios from 'axios';
import { currentLocale } from '@/lib/i18n';

/**
 * The single HTTP client every island uses to reach the backend.
 *
 * Every request goes through `/api`, the same endpoints a native client will
 * consume later. Because Blade and the API are served from the same origin, the
 * browser already holds a session cookie, so Sanctum's stateful middleware can
 * authenticate these calls without any token living in JavaScript.
 */
export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        // Makes Laravel treat the request as AJAX, so it returns JSON errors
        // instead of a redirect to the login page.
        'X-Requested-With': 'XMLHttpRequest',
    },
    // Send the session cookie along with the request.
    withCredentials: true,
    // Since axios 1.6 the XSRF header has to be opted into explicitly. Axios
    // reads the XSRF-TOKEN cookie Laravel sets and mirrors it into the
    // X-XSRF-TOKEN header, which is what Sanctum validates on stateful calls.
    withXSRFToken: true,
});

/**
 * Tells the API which language to answer in.
 *
 * The header is set per request rather than once at creation because the visitor
 * can change language mid-session, and a header baked into the instance would
 * keep announcing the language they started with.
 *
 * `X-Locale` is the second step of the backend's resolution chain, so it works
 * for any client, not just this one: the native app will send the same header and
 * get validation messages in the same language without a cookie ever existing.
 */
api.interceptors.request.use((config) => {
    config.headers.set('X-Locale', currentLocale());

    return config;
});
