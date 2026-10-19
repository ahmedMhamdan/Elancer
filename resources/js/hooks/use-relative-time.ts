import { useTranslation } from '@/hooks/use-translation';

// A time as "in 3 days" or "yesterday" in the page's language, in the largest unit that fits (plan 032).
export function useRelativeTime() {
    const { locale } = useTranslation();
    return (value: string) => {
        const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
        const seconds = (new Date(value).getTime() - Date.now()) / 1000;
        const size = Math.abs(seconds);
        if (size < 3600)
            return format.format(Math.round(seconds / 60), 'minute');
        if (size < 86_400)
            return format.format(Math.round(seconds / 3600), 'hour');
        if (size < 86_400 * 60)
            return format.format(Math.round(seconds / 86_400), 'day');
        return format.format(Math.round(seconds / (86_400 * 30)), 'month');
    };
}
