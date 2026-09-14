import type { Element, Root, RootContent, Text } from 'hast';
import { legalFacts } from '@/content/legal/entity';

type Options = {
    markerPrefix: string;
};

const TOKEN = /\{\{([A-Za-z0-9_]+)\}\}/g;

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
