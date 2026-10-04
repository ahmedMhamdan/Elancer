import { Head, Link, router, usePage } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useNotificationSentence } from '@/hooks/use-notification-sentence';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import '../../../css/elancer-marketplace.css';

type Item = {
    id: string;
    kind: string | null;
    title: string | null;
    actor: string | null;
    read: boolean;
    created_at: string | null;
};

export default function Notifications({ items }: { items: Page<Item> }) {
    const { t, locale } = useTranslation();
    const sentence = useNotificationSentence();
    const unread = usePage().props.notifications.unread;
    return (
        <>
            <Head title={t('Notifications')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <div className="market-actions justify-between">
                    <h1 className="text-2xl font-semibold">
                        {t('Notifications')}
                    </h1>
                    <div className="market-actions">
                        {unread > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        '/notifications/read',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('Mark all as read')}
                            </Button>
                        )}
                        <Link href="/settings/notifications">
                            {t('Email preferences')}
                        </Link>
                    </div>
                </div>
                {items.data.length ? (
                    <ul className="market-panel notification-list">
                        {items.data.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    className="workspace-notification-item"
                                    data-unread={!item.read}
                                    onClick={() =>
                                        router.patch(
                                            `/notifications/${item.id}`,
                                        )
                                    }
                                >
                                    <span className="block text-sm">
                                        {item.actor && (
                                            <bdi className="text-foreground font-medium">
                                                {item.actor}
                                            </bdi>
                                        )}{' '}
                                        <span className="text-muted-foreground">
                                            {sentence(item.kind)}
                                        </span>{' '}
                                        <bdi className="text-foreground font-medium">
                                            {item.title}
                                        </bdi>
                                    </span>
                                    <span className="text-muted-foreground mt-1.5 flex items-center gap-2 text-xs">
                                        {!item.read && (
                                            <span>{t('Unread')}</span>
                                        )}
                                        <time
                                            dateTime={
                                                item.created_at ?? undefined
                                            }
                                        >
                                            {item.created_at
                                                ? new Date(
                                                      item.created_at,
                                                  ).toLocaleString(locale, {
                                                      dateStyle: 'medium',
                                                      timeStyle: 'short',
                                                  })
                                                : ''}
                                        </time>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <section className="market-panel">
                        <p>{t('No notifications yet')}</p>
                        <p className="market-muted">
                            {t('Project and account updates will appear here.')}
                        </p>
                    </section>
                )}
                <Pagination data={items} />
            </main>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
};
