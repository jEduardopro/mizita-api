import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Mounts React islands into the markup Blade rendered.
 *
 * Laravel owns the routing and renders the page; React only takes over the
 * elements that ask for it. A Blade view mounts a component with:
 *
 *     <div data-react-component="Ping"></div>
 *     <div data-react-component="Ping" data-props='{"label":"hi"}'></div>
 *
 * Anything in `data-props` is decoded and handed to the component as initial
 * props. It is meant for small hints like an id, not for page data: data comes
 * from the API so the same endpoints serve the web and, later, the mobile app.
 */

type IslandModule = { default: ComponentType<any> };

// Every component under islands/ is registered by filename. They are bundled
// eagerly; switch to a lazy glob with <Suspense> if the bundle grows.
const modules = import.meta.glob<IslandModule>('./islands/*.tsx', { eager: true });

const islands: Record<string, ComponentType<any>> = Object.fromEntries(
    Object.entries(modules).map(([path, module]) => [
        path.replace('./islands/', '').replace('.tsx', ''),
        module.default,
    ]),
);

document.querySelectorAll<HTMLElement>('[data-react-component]').forEach((element) => {
    const name = element.dataset.reactComponent as string;
    const Component = islands[name];

    if (! Component) {
        console.error(
            `Unknown React island "${name}". Expected resources/js/islands/${name}.tsx.`,
        );

        return;
    }

    const props = element.dataset.props ? JSON.parse(element.dataset.props) : {};

    createRoot(element).render(<Component {...props} />);
});
