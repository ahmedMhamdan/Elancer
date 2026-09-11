import { Head, Link, usePage } from '@inertiajs/react';
import { copy } from './copy';

export default function CategorySecurity({
    configured,
}: {
    configured: boolean;
}) {
    const ar = usePage().props.auth.user.locale === 'ar';
    const t = copy[ar ? 'ar' : 'en'];
    return (
        <div
            className="workspace-dashboard space-y-6"
            dir={ar ? 'rtl' : 'ltr'}
            lang={ar ? 'ar' : 'en'}
        >
            <Head title={t.security} />
            <h1 className="text-3xl font-semibold">{t.security}</h1>
            <p className="text-muted-foreground max-w-2xl">
                {configured ? t.login : t.setup}
            </p>
            <div className="flex flex-wrap gap-6">
                <Link
                    className="text-primary inline-flex min-h-11 items-center underline"
                    href="/settings/security"
                >
                    {t.settings}
                </Link>
                <Link
                    className="text-primary inline-flex min-h-11 items-center underline"
                    href="/logout"
                    method="post"
                    as="button"
                >
                    {t.logout}
                </Link>
            </div>
        </div>
    );
}
