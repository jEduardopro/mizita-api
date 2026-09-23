export function matchesServerField(key: string, serverField: string): boolean {
    return key === serverField || key.startsWith(`${serverField}.`);
}
