export const legalFacts = {
    RETENTION_DAYS: '90',
} as const satisfies Record<string, string>;

export type LegalToken = keyof typeof legalFacts;

export const legalDocuments = {
    terms: { path: '/terms' },
    privacy: { path: '/privacy' },
    cookies: { path: '/cookies' },
} as const satisfies Record<string, { path: string }>;

export type LegalDocumentName = keyof typeof legalDocuments;

export const legalUpdatedOn = {
    terms: '2026-09-12',
    privacy: '2026-09-12',
    cookies: '2026-09-12',
} as const satisfies Record<LegalDocumentName, string>;
