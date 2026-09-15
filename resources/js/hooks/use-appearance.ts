import { useSyncExternalStore } from 'react';
import {
    appearanceSnapshot,
    currentAppearance,
    subscribeToAppearance,
    type Appearance,
    type ResolvedAppearance,
} from '@/lib/appearance';

type AppearanceState = {
    appearance: Appearance;
    resolvedAppearance: ResolvedAppearance;
};

export function useAppearance(): AppearanceState {
    const resolvedAppearance = useSyncExternalStore(subscribeToAppearance, appearanceSnapshot);

    return { appearance: currentAppearance(), resolvedAppearance };
}
