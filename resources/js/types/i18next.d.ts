import 'i18next';

import type admin from '@/locales/en/admin.json';
import type auth from '@/locales/en/auth.json';
import type common from '@/locales/en/common.json';
import type industries from '@/locales/en/industries.json';
import type pub from '@/locales/en/public.json';

/**
 * Key-level type safety for `t()`: i18next reads the catalogue's shape from
 * `CustomTypeOptions`. English is the reference because it is the `fallbackLng`,
 * so it is the one catalogue that must define every key.
 */
declare module 'i18next' {
    interface CustomTypeOptions {
        defaultNS: 'common';
        resources: {
            common: typeof common;
            auth: typeof auth;
            public: typeof pub;
            admin: typeof admin;
            industries: typeof industries;
        };
    }
}
