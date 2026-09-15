import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { arabic } from '@/lib/translations';

export function useTranslation() {
    const { locale } = usePage().props;
    const t = useCallback(
        (text: string, values: Record<string, string | number> = {}) => {
            const message = locale === 'ar' ? (arabic[text] ?? text) : text;
            return message.replace(/:([a-zA-Z_]+)/g, (token, key: string) =>
                Object.hasOwn(values, key)
                    ? '\u2068' + String(values[key]) + '\u2069'
                    : token,
            );
        },
        [locale],
    );
    return {
        locale,
        ar: locale === 'ar',
        t,
    };
}
