import { usePage } from '@inertiajs/react';
import { arabic } from '@/lib/translations';

export function useTranslation() {
    const { locale } = usePage().props;
    return {
        locale,
        ar: locale === 'ar',
        t: (text: string) => (locale === 'ar' ? (arabic[text] ?? text) : text),
    };
}
