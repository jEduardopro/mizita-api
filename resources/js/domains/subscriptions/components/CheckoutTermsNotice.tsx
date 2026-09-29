import type { ReactNode } from 'react';
import { Trans } from 'react-i18next';
import { legalDocuments, type LegalDocumentName } from '@/content/legal/entity';

type LegalTabLinkProps = {
    document: LegalDocumentName;
    children?: ReactNode;
};

function LegalTabLink({ document, children }: LegalTabLinkProps) {
    return (
        <a
            href={legalDocuments[document].path}
            target="_blank"
            rel="noopener noreferrer"
            className="rounded-sm underline underline-offset-2 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            {children}
        </a>
    );
}

export function CheckoutTermsNotice() {
    return (
        <p className="text-xs leading-relaxed text-pretty text-muted-foreground">
            <Trans
                i18nKey="plan.checkout.legal"
                ns="admin"
                components={{
                    terms: <LegalTabLink document="terms" />,
                    privacy: <LegalTabLink document="privacy" />,
                }}
            />
        </p>
    );
}
