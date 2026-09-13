/**
 * Every fact the legal documents interpolate, in one place.
 *
 * The documents are written once and reference these as `{{TOKEN}}`, so
 * changing one is editing this file rather than rewriting prose in six places.
 * A token the documents use and this map does not declare is caught by
 * LegalDocumentParityTest, and renders as a visible marker rather than as a
 * blank in a contract.
 */

export const legalFacts = {
    /**
     * Days between a deletion request and the purge of the soft deleted rows.
     * Every table in the system deletes logically, so this window is what turns
     * that into a real erasure commitment rather than an indefinite hold.
     */
    RETENTION_DAYS: '90',
} as const satisfies Record<string, string>;

export type LegalToken = keyof typeof legalFacts;

/** The three documents, and the URL each one is served from. */
export const legalDocuments = {
    terms: { path: '/terms' },
    privacy: { path: '/privacy' },
    cookies: { path: '/cookies' },
} as const satisfies Record<string, { path: string }>;

export type LegalDocumentName = keyof typeof legalDocuments;

/**
 * When each document last changed, as an ISO date.
 *
 * Bump the entry whose text you edited - the page prints it, and a reader has
 * no other way to tell one revision from another. Mexican data protection law
 * requires that a change of purpose be communicated to the data subject, and
 * this date is what makes "it changed" a checkable claim.
 */
export const legalUpdatedOn = {
    terms: '2026-09-12',
    privacy: '2026-09-12',
    cookies: '2026-09-12',
} as const satisfies Record<LegalDocumentName, string>;
