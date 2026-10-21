// Plan 031: the history and the composer are shared with the workroom's Messages tab (./thread).
import { Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    CheckCheck,
    MoreHorizontal,
    RefreshCw,
    ShieldBan,
} from 'lucide-react';
import { useState } from 'react';
import {
    ChatTemplate,
    type ConversationFilters,
} from '@/components/ui/chat-template';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Page } from '@/pages/discovery/shared';
import {
    MessageLayout,
    type ConversationSummary,
    type Message,
} from './shared';
import { MessageComposer, MessageHistory } from './thread';

type ShowProps = {
    conversation: ConversationSummary;
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    messages: Page<Message>;
    writable: boolean;
    visibleThrough: number;
    fileLimit: number;
};

export default function Show(props: ShowProps) {
    return (
        <MessageLayout>
            <ConversationChat key={props.conversation.id} {...props} />
        </MessageLayout>
    );
}

function ConversationChat({
    conversation,
    conversations,
    filters,
    messages,
    writable,
    visibleThrough,
    fileLimit,
}: ShowProps) {
    const { t, ar } = useTranslation();
    const { demo } = useDemoSession();
    const page = usePage();
    const [refreshing, setRefreshing] = useState(false);
    const header = (
        <div className="flex shrink-0 gap-1">
            <button
                type="button"
                className="chat-icon-button"
                disabled={refreshing}
                aria-label={t('Refresh messages')}
                onClick={() => {
                    setRefreshing(true);
                    router.reload({
                        only: [
                            'messages',
                            'conversation',
                            'conversations',
                            'writable',
                            'visibleThrough',
                        ],
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
            <DropdownMenu dir={ar ? 'rtl' : 'ltr'}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="chat-icon-button"
                        aria-label={t('Conversation actions')}
                    >
                        <MoreHorizontal className="size-5" aria-hidden="true" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link href={'/proposals/' + conversation.proposal_id}>
                            {t('View proposal')}
                        </Link>
                    </DropdownMenuItem>
                    {conversation.contract_id && (
                        <DropdownMenuItem asChild>
                            <Link
                                href={'/contracts/' + conversation.contract_id}
                            >
                                {t('Open contract')}
                            </Link>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    {conversation.unread > 0 && visibleThrough > 0 && (
                        <DropdownMenuItem
                            onSelect={() =>
                                router.patch(
                                    '/messages/' + conversation.id + '/state',
                                    { read_through: visibleThrough },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck aria-hidden="true" />
                            {t('Mark conversation as read')}
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem
                        onSelect={() =>
                            router.patch(
                                '/messages/' + conversation.id + '/state',
                                { archived: !conversation.archived },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {conversation.archived ? (
                            <ArchiveRestore aria-hidden="true" />
                        ) : (
                            <Archive aria-hidden="true" />
                        )}
                        {conversation.archived
                            ? t('Unarchive')
                            : t('Archive conversation')}
                    </DropdownMenuItem>
                    {/* Plan 032 part 10: a shared demo account blocks nobody. */}
                    {!demo && <DropdownMenuSeparator />}
                    {!demo && (
                        <DropdownMenuItem
                            className="text-destructive"
                            onSelect={() => {
                                if (
                                    window.confirm(
                                        t(
                                            'Block this account? New invitations and proposal contact will be stopped.',
                                        ),
                                    )
                                )
                                    router.post(
                                        '/messages/' +
                                            conversation.id +
                                            '/block',
                                    );
                            }}
                        >
                            <ShieldBan aria-hidden="true" />
                            {t('Block account')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
    const composer = (
        <MessageComposer
            url={page.url}
            writable={writable}
            fileLimit={fileLimit}
        />
    );
    return (
        <ChatTemplate
            conversations={conversations}
            filters={filters}
            selected={conversation}
            header={header}
            composer={composer}
        >
            <MessageHistory
                conversationId={conversation.id}
                messages={messages}
                counterpart={conversation.counterpart}
                busy={refreshing}
            />
        </ChatTemplate>
    );
}
