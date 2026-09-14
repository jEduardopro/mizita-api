/**
 * Every fact the legal documents interpolate as `{{TOKEN}}`. An undeclared token
 * is caught by LegalDocumentParityTest and renders as a visible marker rather
 * than as a blank in a contract.
 */

export const legalFacts = {
    /** Days between a deletion request and the purge of the soft deleted rows. */
    RETENTION_DAYS: '90',
} as const satisfies Record<string, string>;

export type LegalToken = keyof typeof legalFacts;

export const legalDocuments = {
    terms: { path: '/terms' },
    privacy: { path: '/privacy' },
    cookies: { path: '/cookies' },
} as const satisfies Record<string, { path: string }>;

export type LegalDocumentName = keyof typeof legalDocuments;

/**
 * Bump the entry whose text you edited. Mexican data protection law requires a
 * change of purpose to be communicated, and this date is what makes "it changed"
 * a checkable claim.
 */
export const legalUpdatedOn = {
    terms: '2026-09-12',
    privacy: '2026-09-12',
    cookies: '2026-09-12',
} as const satisfies Record<LegalDocumentName, string>;
