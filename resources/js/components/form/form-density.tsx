import { createContext, useContext, type ReactNode } from 'react';

export type FormDensity = 'comfortable' | 'compact';

export const CONTROL_DENSITY_CLASSES: Record<FormDensity, string> = {
    comfortable: 'h-11 text-base md:text-base',
    compact: 'h-9 text-sm',
};

export const CONTROL_TEXT_DENSITY_CLASSES: Record<FormDensity, string> = {
    comfortable: 'text-base md:text-base',
    compact: 'text-sm',
};

export const TEXTAREA_DENSITY_CLASSES: Record<FormDensity, string> = {
    comfortable: 'min-h-28 text-base md:text-base',
    compact: 'min-h-20 text-sm',
};

const FormDensityContext = createContext<FormDensity>('comfortable');

type Props = {
    density: FormDensity;
    children: ReactNode;
};

export function FormDensityProvider({ density, children }: Props) {
    return <FormDensityContext.Provider value={density}>{children}</FormDensityContext.Provider>;
}

export function useFormDensity(): FormDensity {
    return useContext(FormDensityContext);
}
