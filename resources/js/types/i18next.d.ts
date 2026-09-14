import 'i18next';

import type admin from '@/locales/en/admin.json';
import type auth from '@/locales/en/auth.json';
import type common from '@/locales/en/common.json';
import type industries from '@/locales/en/industries.json';
import type pub from '@/locales/en/public.json';

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
