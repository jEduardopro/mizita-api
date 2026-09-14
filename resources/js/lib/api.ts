import axios from 'axios';
import { currentLocale } from '@/lib/i18n';

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
