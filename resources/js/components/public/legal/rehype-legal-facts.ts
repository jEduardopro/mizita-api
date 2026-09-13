import type { Element, Root, RootContent, Text } from 'hast';
import { legalFacts } from '@/content/legal/entity';

/**
 * Resolves the `{{TOKEN}}` placeholders the documents are written with.
 *
 * It runs over the parsed tree rather than over the markdown string, for two
 * reasons. A value can then never introduce markup — a value with a `*` in it
 * stays text instead of becoming emphasis — and a token nobody declared can be
 * replaced by an *element*, which a string substitution could never produce.
 *
 * That element is the point. A document referring to a fact that does not exist
 * renders a marker naming the token, so the gap is visible to anyone who opens
 * the page. The alternative, an empty string, would quietly publish a contract
 * with a blank in it.
 */

type Options = {
    /** `POR DEFINIR` / `TO BE DEFINED` — translated by the caller. */
    markerPrefix: string;
};

const TOKEN = /\{\{([A-Za-z0-9_]+)\}\}/g;

/**
 * `mark` is the one element this pipeline emits itself: GFM has no highlight
 * syntax, so nothing in the documents can produce one by accident, and the
 * renderer is free to read every `mark` it receives as a marker.
 */
function marker(label: string, markerPrefix: string): Element {
    return {
        type: 'element',
        tagName: 'mark',
        properties: {},
        children: [{ type: 'text', value: `[ ${markerPrefix}: ${label} ]` }],
    };
}

function findFact(token: string): string | undefined {
    const facts: Record<string, string> = legalFacts;

    return facts[token];
}

function resolve(token: string, { markerPrefix }: Options): Element | Text {
    const value = findFact(token);

    // An unknown token is a document referring to a fact nobody declared, so it
    // names itself instead of vanishing, and is impossible to mistake for
    // finished prose.
    if (value === undefined) {
        return marker(token, markerPrefix);
    }

    return { type: 'text', value };
}

function expandText(value: string, options: Options): RootContent[] {
    const nodes: RootContent[] = [];
    let cursor = 0;

    for (const match of value.matchAll(TOKEN)) {
        const start = match.index;

        if (start > cursor) {
            nodes.push({ type: 'text', value: value.slice(cursor, start) });
        }

        nodes.push(resolve(match[1], options));
        cursor = start + match[0].length;
    }

    if (cursor < value.length) {
        nodes.push({ type: 'text', value: value.slice(cursor) });
    }

    return nodes;
}

function expandTree(node: Root | Element, options: Options): void {
    const expanded: RootContent[] = [];

    for (const child of node.children) {
        if (child.type === 'element') {
            expandTree(child, options);
        }

        if (child.type === 'text' && child.value.includes('{{')) {
            expanded.push(...expandText(child.value, options));

            continue;
        }

        expanded.push(child);
    }

    node.children = expanded;
}

/** Builds the plugin for one document, in one language. */
export function rehypeLegalFacts(options: Options) {
    return () => (tree: Root) => {
        expandTree(tree, options);
    };
}
