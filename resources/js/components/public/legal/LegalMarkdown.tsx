import { Link } from '@inertiajs/react';
import type { Element } from 'hast';
import { useMemo, type ReactNode } from 'react';
import Markdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { slugify } from '@/components/public/legal/legal-content';
import { rehypeLegalFacts } from '@/components/public/legal/rehype-legal-facts';

const LINK_CLASS = 'rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50';

const ABSOLUTE_URL = /^https?:\/\//;

function headingText(node: Element | undefined): string {
    if (node === undefined) {
        return '';
    }

    let text = '';

    for (const child of node.children) {
        if (child.type === 'text') {
            text += child.value;
        }

        if (child.type === 'element') {
            text += headingText(child);
        }
    }

    return text;
}

type AnchorProps = {
    href?: string;
    children?: ReactNode;
};

function LegalAnchor({ href, children }: AnchorProps) {
    if (href === undefined) {
        return <>{children}</>;
    }

    if (href.startsWith('/')) {
        return (
            <Link href={href} className={LINK_CLASS}>
                {children}
            </Link>
        );
    }

    if (ABSOLUTE_URL.test(href)) {
        return (
            <a href={href} target="_blank" rel="noopener noreferrer" className={LINK_CLASS}>
                {children}
            </a>
        );
    }

    return (
        <a href={href} className={LINK_CLASS}>
            {children}
        </a>
    );
}

const components: Components = {
    a: LegalAnchor,

    h2: ({ children, node }) => (
        <h2 id={slugify(headingText(node))} tabIndex={-1} className="scroll-mt-20 outline-none">
            {children}
        </h2>
    ),

    mark: ({ children }) => (
        <mark className="mx-0.5 box-decoration-clone rounded-md border border-warning/30 bg-warning-surface px-1.5 py-0.5 text-[0.8125em] font-medium text-warning">
            {children}
        </mark>
    ),

    table: ({ children }) => (
        <div
            tabIndex={0}
            className="my-8 w-full overflow-x-auto rounded-xl border border-border outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            <table className="my-0">{children}</table>
        </div>
    ),
};

type Props = {
    body: string;
    markerPrefix: string;
};

export function LegalMarkdown({ body, markerPrefix }: Props) {
    const rehypePlugins = useMemo(() => [rehypeLegalFacts({ markerPrefix })], [markerPrefix]);

    return (
        <div className="prose prose-legal min-w-0 max-w-none">
            <Markdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={rehypePlugins}
                components={components}
            >
                {body}
            </Markdown>
        </div>
    );
}
