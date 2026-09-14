import type { Element, Root, RootContent, Text } from 'hast';
import { legalFacts } from '@/content/legal/entity';

/**
 * Resolves the `{{TOKEN}}` placeholders the documents are written with, over the
 * parsed tree rather than the markdown string: a value can then never introduce
 * markup, and an undeclared token can be replaced by an *element* — a marker
 * naming the gap — which a string substitution could never produce. An empty
 * string would quietly publish a contract with a blank in it.
 */

type Options = {
    /** `POR DEFINIR` / `TO BE DEFINED` — translated by the caller. */
    markerPrefix: string;
};

const TOKEN = /\{\{([A-Za-z0-9_]+)\}\}/g;

/**
 * `mark` is the one element this pipeline emits itself: GFM has no highlight
 * syntax, so the renderer can read every `mark` it receives as a marker.
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

export function rehypeLegalFacts(options: Options) {
    return () => (tree: Root) => {
        expandTree(tree, options);
    };
}
