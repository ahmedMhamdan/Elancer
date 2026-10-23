// Plan 031 (Q31): the contract's conversation as a workroom tab, with the Messages page's history and composer.
import { Link, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConversationAvatar } from '@/components/ui/chat-template';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Page } from '@/pages/discovery/shared';
import type { Message } from '@/pages/messages/shared';
import { MessageComposer, MessageHistory } from '@/pages/messages/thread';
import '../../../css/elancer-chat.css';

export type Thread = {
    id: number;
    counterpart: string;
    unread: number;
    messages: Page<Message>;
    writable: boolean;
    visibleThrough: number;
    fileLimit: number;
};

export function ContractMessages({ thread }: { thread: Thread }) {
    const { t } = useTranslation();
    const [refreshing, setRefreshing] = useState(false);
    const latestPage = thread.messages.current_page === 1;
    // A view-only demo session changes nothing, read marks included on this tab (the request would be refused).
    const { demo, demoActing } = usePage().props.auth;
    const canRead = !demo || demoActing;
    // Plan 032 part 13: the newest messages are on screen, so the conversation is read, as on the Messages page.
    // Not while the browser tab is hidden; it is read once the member comes back to it.
    useEffect(() => {
        const read = () => {
            if (
                document.hidden ||
                !canRead ||
                !latestPage ||
                thread.unread === 0 ||
                thread.visibleThrough === 0
            )
                return;
            router.patch(
                `/messages/${thread.id}/state`,
                { read_through: thread.visibleThrough },
                { preserveScroll: true, preserveState: true },
            );
        };
        read();
        document.addEventListener('visibilitychange', read);
        return () => document.removeEventListener('visibilitychange', read);
    }, [thread.id, thread.unread, thread.visibleThrough, latestPage, canRead]);
    return (
        <section
            className="chat-window contract-chat"
            aria-label={t('Messages')}
        >
            <header className="chat-header">
                <ConversationAvatar name={thread.counterpart} size={40} />
                <div className="chat-header-text">
                    <h2>
                        <bdi>{thread.counterpart}</bdi>
                    </h2>
                </div>
                <button
                    type="button"
                    className="chat-icon-button"
                    disabled={refreshing}
                    aria-label={t('Refresh messages')}
                    title={t('Refresh messages')}
                    onClick={() => {
                        setRefreshing(true);
                        router.reload({
                            only: ['thread', 'inbox', 'notifications'],
                            onFinish: () => setRefreshing(false),
                        });
                    }}
                >
                    <RefreshCw
                        className={cn(
                            'size-4',
                            refreshing && 'motion-safe:animate-spin',
                        )}
                        aria-hidden="true"
                    />
                </button>
                <Link
                    href={`/messages/${thread.id}`}
                    className="chat-pill-button"
                >
                    {t('Open messages')}
                </Link>
            </header>
            <MessageHistory
                conversationId={thread.id}
                messages={thread.messages}
                counterpart={thread.counterpart}
                busy={refreshing}
            />
            <MessageComposer
                url={`/messages/${thread.id}`}
                writable={thread.writable}
                fileLimit={thread.fileLimit}
                extra={{ workroom: true }}
            />
        </section>
    );
}
