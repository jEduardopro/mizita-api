type Tone = 'muted' | 'surface' | 'brand-50' | 'brand-100' | 'brand-200' | 'brand-300';

type Shape = '1x1' | '2x1' | '1x2' | '2x2';

type Tile = {
    tone: Tone;
    shape?: Shape;
};

const toneClasses: Record<Tone, string> = {
    muted: 'bg-muted',
    surface: 'bg-surface-muted',
    'brand-50': 'bg-brand-50 dark:bg-brand-950/60',
    'brand-100': 'bg-brand-100 dark:bg-brand-900/55',
    'brand-200': 'bg-brand-200 dark:bg-brand-800/45',
    'brand-300': 'bg-brand-300/70 dark:bg-brand-700/45',
};

const shapeClasses: Record<Shape, string> = {
    '1x1': 'aspect-square',
    '2x1': 'col-span-2 aspect-[2/1]',
    '1x2': 'row-span-2',
    '2x2': 'col-span-2 row-span-2',
};

const tiles: readonly Tile[] = [
    { tone: 'brand-100', shape: '2x2' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-50' },
    { tone: 'brand-300' },

    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted', shape: '1x2' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200', shape: '2x1' },

    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },

    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },

    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },

    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },

    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },

    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '1x2' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-300' },

    { tone: 'muted' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200' },

    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'muted' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },

    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },

    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x2' },
    { tone: 'brand-50' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200' },

    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'muted' },
    { tone: 'brand-100', shape: '2x1' },
    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },

    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'surface' },
    { tone: 'brand-50', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'muted' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },
    { tone: 'brand-50' },

    { tone: 'brand-200' },
    { tone: 'brand-50' },
    { tone: 'brand-100' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'muted' },
    { tone: 'brand-100' },
    { tone: 'surface' },
    { tone: 'brand-200' },
    { tone: 'brand-100' },
    { tone: 'brand-300' },
    { tone: 'brand-200', shape: '2x1' },
    { tone: 'brand-100' },
];

type Layer = {
    focus: 'far' | 'near' | 'sharp';
    blur: string;
    mask: string;
};

const layers: readonly Layer[] = [
    {
        focus: 'far',
        blur: 'blur-2xl',
        mask: '[mask-image:linear-gradient(to_left,transparent_62%,black_88%,black_100%)]',
    },
    {
        focus: 'near',
        blur: 'blur-md',
        mask: '[mask-image:linear-gradient(to_left,transparent_24%,black_50%,black_70%,transparent_92%)]',
    },
    {
        focus: 'sharp',
        blur: '',
        mask: '[mask-image:linear-gradient(to_left,black_0%,black_26%,transparent_58%)]',
    },
];

export function TileBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-background"
        >
            {layers.map((layer) => (
                <div key={layer.focus} className={`absolute inset-0 ${layer.mask}`}>
                    <div className={`size-full scale-125 ${layer.blur}`}>
                        <TileGrid />
                    </div>
                </div>
            ))}

            <div className="absolute inset-0 bg-gradient-to-l from-transparent via-background/10 to-background/24" />
        </div>
    );
}

function TileGrid() {
    return (
        <div className="grid grid-flow-dense grid-cols-5 gap-2 p-2 sm:grid-cols-8 sm:gap-2.5 sm:p-2.5 lg:grid-cols-12 xl:grid-cols-16">
            {tiles.map((tile, index) => (
                <span
                    key={index}
                    className={`rounded-xl border border-brand-200/40 dark:border-brand-800/40 ${toneClasses[tile.tone]} ${shapeClasses[tile.shape ?? '1x1']}`}
                />
            ))}
        </div>
    );
}
