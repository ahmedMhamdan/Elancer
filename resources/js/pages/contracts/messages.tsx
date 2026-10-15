// Plan 031 (Q31): the contract's conversation as a workroom tab, with the Messages page's history and composer.
import { Link, router } from '@inertiajs/react';
import { CheckCheck, RefreshCw } from 'lucide-react';
import { useState } from 'react';
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
    return (
        <section
            className="chat-window contract-chat"
            aria-label={t('Messages')}
        >
            <header className="chat-header">
                <ConversationAvatar name={thread.counterpart} />
                <div className="min-w-0 flex-1">
                    <h2 className="truncate font-semibold" dir="auto">
                        {thread.counterpart}
                    </h2>
                    {thread.unread > 0 && (
                        <p className="text-muted-foreground text-sm">
                            {t(':count unread', { count: thread.unread })}
                        </p>
                    )}
                </div>
                <div className="flex shrink-0 flex-wrap items-center justify-end gap-1">
                    <button
                        type="button"
                        className="chat-icon-button"
                        disabled={refreshing}
                        aria-label={t('Refresh messages')}
                        onClick={() => {
                            setRefreshing(true);
                            router.reload({
                                only: ['thread'],
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
                    {thread.unread > 0 && thread.visibleThrough > 0 && (
                        <button
                            type="button"
                            className="chat-icon-button"
                            aria-label={t('Mark conversation as read')}
                            title={t('Mark conversation as read')}
                            onClick={() =>
                                router.patch(
                                    `/messages/${thread.id}/state`,
                                    { read_through: thread.visibleThrough },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck className="size-4" aria-hidden="true" />
                        </button>
                    )}
                    <Link
                        href={`/messages/${thread.id}`}
                        className="contract-chat-open"
                    >
                        {t('Open messages')}
                    </Link>
                </div>
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
