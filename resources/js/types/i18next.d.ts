import 'i18next';

import type admin from '@/locales/en/admin.json';
import type auth from '@/locales/en/auth.json';
import type common from '@/locales/en/common.json';
import type industries from '@/locales/en/industries.json';
import type pub from '@/locales/en/public.json';

/**
 * Key-level type safety for `t()`.
 *
 * i18next reads the shape of the catalogue from `CustomTypeOptions`, so pointing
 * it at the JSON files makes every namespace and every key part of the type
 * system: `t('welcome.headine')` stops compiling, and so does calling `t` with a
 * key that belongs to another namespace.
 *
 * English is the reference on purpose — it is the `fallbackLng`, so it is the one
 * catalogue that must define every key. Spanish is checked against it by having
 * the same shape; a key added to `es` alone would simply never be reachable.
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
