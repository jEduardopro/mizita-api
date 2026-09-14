import { Link } from '@inertiajs/react';
import type { Element } from 'hast';
import { useMemo, type ReactNode } from 'react';
import Markdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { slugify } from '@/components/public/legal/legal-content';
import { rehypeLegalFacts } from '@/components/public/legal/rehype-legal-facts';

/** The one rule every link in a document is set by, internal or not. */
const LINK_CLASS = 'rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50';

const ABSOLUTE_URL = /^https?:\/\//;

/**
 * Read from the parsed tree rather than the markdown line, so a heading that
 * gains emphasis or a link still produces the anchor the index computed.
 */
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

/**
 * The documents cross-reference each other by path, and those are pages of this
 * app, so they travel through Inertia. Anything absolute leaves the site and
 * opens in its own tab, without handing the destination a referrer.
 */
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
        // `tabIndex` moves reading position with scroll position, so the next Tab
        // continues inside the clause rather than back at the header.
        <h2 id={slugify(headingText(node))} tabIndex={-1} className="scroll-mt-20 outline-none">
            {children}
        </h2>
    ),

    // A fact the paperwork has not produced yet, deliberately the loudest thing
    // on the page until `entity.ts` carries the value.
    mark: ({ children }) => (
        // `box-decoration-clone` so a marker long enough to wrap keeps its
        // padding and its corners on every line instead of on the first only.
        <mark className="mx-0.5 box-decoration-clone rounded-md border border-warning/30 bg-warning-surface px-1.5 py-0.5 text-[0.8125em] font-medium text-warning">
            {children}
        </mark>
    ),

    // The scroll belongs to the table, not the page: the container takes focus so
    // it is reachable without a pointer.
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
    /** The document with its title removed — the page sets that itself. */
    body: string;
    /** What an undeclared fact is called, translated by the caller. */
    markerPrefix: string;
};

export function LegalMarkdown({ body, markerPrefix }: Props) {
    const rehypePlugins = useMemo(() => [rehypeLegalFacts({ markerPrefix })], [markerPrefix]);

    return (
        // `min-w-0` keeps the tables inside their own scroll container: without
        // it a grid item grows to its widest child and the page scrolls sideways.
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
