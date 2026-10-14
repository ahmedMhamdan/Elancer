import { Link } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

// Plan 029: one quiet link from a page to the Help article that explains it.
export default function HelpLink({
    article,
    className = '',
}: {
    article: string;
    className?: string;
}) {
    const { t } = useTranslation();
    return (
        <Link
            href={`/help#${article}`}
            data-test="help-link"
            className={`text-muted-foreground hover:text-foreground inline-flex min-h-6 items-center gap-1.5 text-sm underline underline-offset-4 ${className}`}
        >
            <CircleHelp size={16} aria-hidden="true" />
            {t('Read more in Help')}
        </Link>
    );
}
