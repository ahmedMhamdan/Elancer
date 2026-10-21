import { useCallback } from 'react';

export type GetInitialsFn = (fullName: string) => string;

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function useInitials(): GetInitialsFn {
    return useCallback((fullName: string): string => {
        // Plan 032 part 10: only words that start with a letter, so "Nadia Farah (Demo)" gives NF, not N(.
        const names = fullName
            .trim()
            .split(/\s+/u)
            .filter((name) => /^\p{L}/u.test(name));

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return getInitial(names[0]).toUpperCase();
        }

        const firstInitial = getInitial(names[0]);
        const lastInitial = getInitial(names[names.length - 1]);

        return `${firstInitial}${lastInitial}`.toUpperCase();
    }, []);
}
