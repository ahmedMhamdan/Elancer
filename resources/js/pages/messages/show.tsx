// Plan 031: the history and the composer are shared with the workroom's Messages tab (./thread).
// Plan 032 part 13 (option A): the header names the project and its state and offers the one next step; the details
// open beside the chat on a wide screen and in a sheet otherwise.
import { Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    ArrowRight,
    BriefcaseBusiness,
    Info,
    MoreHorizontal,
    RefreshCw,
    ShieldBan,
} from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import {
    BackToList,
    ChatTemplate,
    ConversationAvatar,
    StatusMark,
    type ConversationFilters,
} from '@/components/ui/chat-template';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Page } from '@/pages/discovery/shared';
import { blockAccount, DetailsPanel, useNextStep } from './details';
import {
    MessageLayout,
    type ConversationDetails,
    type ConversationSummary,
    type Message,
} from './shared';
import { MessageComposer, MessageHistory } from './thread';

type ShowProps = {
    conversation: ConversationSummary;
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    firstUnread: number | null;
    newMessages: number;
    details: ConversationDetails;
    messages: Page<Message>;
    writable: boolean;
    visibleThrough: number;
    fileLimit: number;
};

// What a refresh asks for again: the conversation, the list beside it and the counts.
export const conversationProps = [
    'messages',
    'conversation',
    'conversations',
    'details',
    'writable',
    'visibleThrough',
    'inbox',
    'notifications',
];

function useMedia(query: string) {
    return useSyncExternalStore(
        (change) => {
            const list = window.matchMedia(query);
            list.addEventListener('change', change);
            return () => list.removeEventListener('change', change);
        },
        () => window.matchMedia(query).matches,
        () => false,
    );
}

const DETAILS = 'elancer.chat.details';

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
    firstUnread,
    newMessages,
    details,
    messages,
    writable,
    fileLimit,
}: ShowProps) {
    const { t, ar } = useTranslation();
    const { demo } = useDemoSession();
    const next = useNextStep(conversation);
    const wide = useMedia('(min-width: 1280px)');
    const phone = useMedia('(max-width: 767px)');
    const [refreshing, setRefreshing] = useState(false);
    // A wide screen remembers whether the details were left open; a sheet always starts closed.
    const [open, setOpen] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(min-width: 1280px)').matches &&
            window.localStorage.getItem(DETAILS) === 'open',
    );
    const toggle = (value: boolean) => {
        setOpen(value);
        if (wide) window.localStorage.setItem(DETAILS, value ? 'open' : '');
    };
    const c = conversation;
    const first = c.counterpart.trim().split(/\s+/u)[0] ?? c.counterpart;
    const Arrow = ar ? ArrowLeft : ArrowRight;
    // A send returns to the list as it was, never to an older page of the history.
    const query = new URLSearchParams(usePage().url.split('?')[1] ?? '');
    query.delete('page');
    const url =
        `/messages/${c.id}` + (query.size ? '?' + query.toString() : '');
    const refresh = () => {
        setRefreshing(true);
        router.reload({
            only: conversationProps,
            onFinish: () => setRefreshing(false),
        });
    };
    const header = (
        <header className="chat-header">
            <BackToList filters={filters} />
            <ConversationAvatar
                name={c.counterpart}
                src={c.avatar}
                size={phone ? 36 : 40}
            />
            <div className="chat-header-text">
                <h2>
                    <bdi>{c.counterpart}</bdi>
                    {demo && <span className="chat-demo-tag">{t('Demo')}</span>}
                </h2>
                <p>
                    <Link href={`/jobs/${c.project.id}`}>
                        <bdi>{c.project.title}</bdi>
                    </Link>
                    <span aria-hidden="true" className="chat-header-dot">
                        ·
                    </span>
                    <StatusMark status={c.status} />
                </p>
            </div>
            {next.action && next.href && (
                <Link
                    href={next.href}
                    className="chat-pill-button chat-pill-button-primary chat-header-action"
                >
                    {next.action}
                </Link>
            )}
            <button
                type="button"
                className="chat-icon-button"
                aria-pressed={open}
                aria-label={t('Conversation details')}
                title={t('Conversation details')}
                onClick={() => toggle(!open)}
            >
                <Info className="size-5" aria-hidden="true" />
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
                <DropdownMenuContent align="end" className="min-w-56">
                    {next.action && next.href && (
                        <DropdownMenuItem asChild className="sm:hidden">
                            <Link href={next.href}>{next.action}</Link>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem asChild>
                        <Link href={`/proposals/${c.proposal_id}`}>
                            <Arrow aria-hidden="true" />
                            {t('View proposal')}
                        </Link>
                    </DropdownMenuItem>
                    {c.contract_id && (
                        <DropdownMenuItem asChild>
                            <Link href={`/contracts/${c.contract_id}`}>
                                <BriefcaseBusiness aria-hidden="true" />
                                {t('Open contract')}
                            </Link>
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem disabled={refreshing} onSelect={refresh}>
                        <RefreshCw
                            className={cn(
                                refreshing && 'motion-safe:animate-spin',
                            )}
                            aria-hidden="true"
                        />
                        {t('Refresh messages')}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={() =>
                            router.patch(
                                `/messages/${c.id}/state`,
                                { archived: !c.archived },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {c.archived ? (
                            <ArchiveRestore aria-hidden="true" />
                        ) : (
                            <Archive aria-hidden="true" />
                        )}
                        {c.archived
                            ? t('Unarchive')
                            : t('Archive conversation')}
                    </DropdownMenuItem>
                    {/* Plan 032 part 10: a shared demo account blocks nobody. */}
                    {!demo && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => blockAccount(c.id, t)}
                        >
                            <ShieldBan aria-hidden="true" />
                            {t('Block account')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
    const start = (
        <div className="chat-start">
            <ConversationAvatar name={c.counterpart} src={c.avatar} size={64} />
            <bdi className="chat-start-name">{c.counterpart}</bdi>
            <p dir="auto">{c.project.title}</p>
            <p>
                {c.is_client
                    ? t(
                          'You started this conversation about :name’s proposal.',
                          {
                              name: first,
                          },
                      )
                    : t(
                          ':name started this conversation about your proposal.',
                          {
                              name: first,
                          },
                      )}
            </p>
            <Link
                href={`/proposals/${c.proposal_id}`}
                className="chat-pill-button"
            >
                {t('View proposal')}
            </Link>
        </div>
    );
    return (
        <ChatTemplate
            conversations={conversations}
            filters={filters}
            selected={conversation}
        >
            <div className="chat-main">
                <div className="chat-column">
                    {header}
                    <MessageHistory
                        conversationId={c.id}
                        messages={messages}
                        counterpart={c.counterpart}
                        avatar={c.avatar}
                        busy={refreshing}
                        firstUnread={firstUnread}
                        newMessages={newMessages}
                        start={start}
                    />
                    <MessageComposer
                        url={url}
                        writable={writable}
                        fileLimit={fileLimit}
                    />
                </div>
                {wide && (
                    <aside
                        className={cn('chat-info', open && 'chat-info-open')}
                        aria-label={t('Conversation details')}
                        inert={!open}
                    >
                        <div>
                            <DetailsPanel conversation={c} details={details} />
                        </div>
                    </aside>
                )}
            </div>
            {!wide && (
                <Sheet open={open} onOpenChange={toggle}>
                    <SheetContent
                        side={phone ? 'bottom' : ar ? 'left' : 'right'}
                        className={cn(
                            'chat-sheet gap-0 overflow-y-auto p-0',
                            phone && 'max-h-[85dvh] rounded-t-2xl',
                        )}
                    >
                        <SheetHeader className="sr-only">
                            <SheetTitle>{t('Conversation details')}</SheetTitle>
                            <SheetDescription>
                                {c.project.title}
                            </SheetDescription>
                        </SheetHeader>
                        <DetailsPanel conversation={c} details={details} />
                    </SheetContent>
                </Sheet>
            )}
        </ChatTemplate>
    );
}
