import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { legalDocuments } from '@/content/legal/entity';

type Props = {
    document: 'terms' | 'privacy';
    children?: ReactNode;
};

export function LegalLink({ document, children }: Props) {
    return (
        <Link
            href={legalDocuments[document].path}
            className="rounded-sm underline underline-offset-2 transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            {children}
        </Link>
    );
}
