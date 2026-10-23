// Adapted from Ahmed's supplied 21st.dev Chat template by Manoj Rayi (MIT).
// Plan 032 part 13 (option A): a focused chat in Instagram's manner inside Elancer's sidebar: the list of
// conversations with the deal behind each, and the open conversation beside it (alone on a phone).
import { Link, router, usePage } from '@inertiajs/react';
import { Archive, ArrowLeft, ArrowRight, ChevronLeft, ChevronRight, MessageCircle, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Page } from '@/pages/discovery/shared';
import type { ConversationSummary } from '@/pages/messages/shared';

export type ConversationFilters = {
    kind: '' | 'hiring' | 'contracts';
    archived: boolean;
    unread: boolean;
    q: string;
};

export function queryFor(filters: ConversationFilters) {
    const query = new URLSearchParams();
    if (filters.kind) query.set('kind', filters.kind);
    if (filters.archived) query.set('archived', '1');
    if (filters.unread) query.set('unread', '1');
    if (filters.q) query.set('q', filters.q);
    return query;
}

/** A photo when the member may see one, otherwise initials on a colour of their own. */
export function ConversationAvatar({ name, src, size = 48 }: { name: string; src?: string | null; size?: number }) {
    const words = name.trim().split(/\s+/u).filter((word) => /\p{L}/u.test(Array.from(word)[0] ?? ''));
    const initials = words.slice(0, 2).map((word) => Array.from(word)[0]).join('');
    let hue = 0;
    for (const letter of name) hue = (hue * 31 + (letter.codePointAt(0) ?? 0)) % 360;
    return (
        <Avatar className="chat-avatar" style={{ width: size, height: size, fontSize: Math.round(size * 0.36), ['--chat-hue' as string]: hue }} aria-hidden="true">
            {src && <AvatarImage src={src} alt="" className="object-cover" />}
            <AvatarFallback className="chat-avatar-initials">{initials}</AvatarFallback>
        </Avatar>
    );
}

// The contract's state as Contracts shows it; a hollow square while it is still hiring.
const STATE: Record<string, string> = {
    awaiting_payment: 'wait',
    active: 'open',
    submitted: 'hired',
    revision_requested: 'closed',
    cancellation_pending: 'hidden',
    completed: 'draft',
    cancelled: 'hidden',
};

export function useStatusLabel() {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        awaiting_payment: t('Awaiting funding'),
        active: t('In progress'),
        submitted: t('Delivered, awaiting review'),
        revision_requested: t('Revision requested'),
        cancellation_pending: t('Cancellation pending'),
        completed: t('Completed'),
        cancelled: t('Cancelled'),
    };
    return (status: string | null) => (status === null ? t('Hiring') : (labels[status] ?? status));
}

export function StatusMark({ status, label = true }: { status: string | null; label?: boolean }) {
    const name = useStatusLabel()(status);
    return (
        <span className="chat-status" data-state={status === null ? 'wait' : (STATE[status] ?? 'draft')} title={label ? undefined : name}>
            {label ? name : <span className="sr-only">{name}</span>}
        </span>
    );
}

/** Times as messaging apps write them: the hour today, then the day, then the date. */
export function useChatTime() {
    const { t, locale } = useTranslation();
    const days = (value: string) => {
        const start = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
        return Math.round((start(new Date()) - start(new Date(value))) / 86_400_000);
    };
    const format = (value: string, options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat(locale, options).format(new Date(value));
    const clock = (value: string) => format(value, { hour: 'numeric', minute: '2-digit' });
    const day = (value: string) => {
        const ago = days(value);
        if (ago === 0) return t('Today');
        if (ago === 1) return t('Yesterday');
        if (ago < 7) return format(value, { weekday: 'long' });
        return format(value, { month: 'long', day: 'numeric', ...(new Date(value).getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) });
    };
    return {
        clock,
        // Beside a conversation in the list.
        list: (value: string) => {
            const ago = days(value);
            if (ago === 0) return clock(value);
            if (ago === 1) return t('Yesterday');
            if (ago < 7) return format(value, { weekday: 'short' });
            return format(value, { month: 'short', day: 'numeric', ...(new Date(value).getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) });
        },
        // Between messages after a pause.
        stamp: (value: string) => `${day(value)} ${clock(value)}`,
        full: (value: string) => format(value, { dateStyle: 'full', timeStyle: 'short' }),
        sameDay: (a: string, b: string) => days(a) === days(b),
    };
}

export function ChatTemplate({
    conversations,
    filters,
    selected,
    children,
}: {
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    selected?: ConversationSummary;
    children?: ReactNode;
}) {
    const { t } = useTranslation();
    return (
        <div className="chat-template" data-view={selected ? 'thread' : 'list'}>
            <aside className="chat-list" aria-label={t('Conversations')}>
                <ConversationList key={JSON.stringify(filters)} conversations={conversations} filters={filters} selected={selected} />
            </aside>
            <section className="chat-window" aria-label={t('Messages')}>
                {selected ? (
                    children
                ) : (
                    <div className="chat-empty">
                        <span className="chat-empty-mark">
                            <MessageCircle className="size-8" aria-hidden="true" />
                        </span>
                        <h2>{t('Your messages')}</h2>
                        <p>{t('Keep project discussions and agreements together. Choose a conversation to read and reply.')}</p>
                    </div>
                )}
            </section>
        </div>
    );
}

/** Where the back arrow of an open conversation leads: the list as it was left. */
export function useInboxUrl(filters: ConversationFilters) {
    const page = usePage();
    const query = queryFor(filters);
    const listPage = new URLSearchParams(page.url.split('?')[1] ?? '').get('list_page');
    if (listPage) query.set('page', listPage);
    return '/messages' + (query.size ? '?' + query.toString() : '');
}

export function BackToList({ filters }: { filters: ConversationFilters }) {
    const { t, ar } = useTranslation();
    const BackIcon = ar ? ArrowRight : ArrowLeft;
    return (
        <Link href={useInboxUrl(filters)} className="chat-back chat-icon-button" aria-label={t('Back to conversations')}>
            <BackIcon className="size-5" aria-hidden="true" />
        </Link>
    );
}

function ConversationList({ conversations, filters, selected }: {
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    selected?: ConversationSummary;
}) {
    const { t, ar } = useTranslation();
    const time = useChatTime();
    const { inbox } = usePage().props;
    const [search, setSearch] = useState(filters.q);
    const PreviousIcon = ar ? ChevronRight : ChevronLeft;
    const NextIcon = ar ? ChevronLeft : ChevronRight;
    function filterUrl(changes: Partial<ConversationFilters>) {
        const query = queryFor({ ...filters, ...changes });
        return '/messages' + (query.size ? '?' + query.toString() : '');
    }
    function conversationUrl(id: number) {
        const query = queryFor(filters);
        if (conversations.current_page > 1) query.set('list_page', String(conversations.current_page));
        return '/messages/' + id + (query.size ? '?' + query.toString() : '');
    }
    // One chip at a time: the whole inbox, what is unread, or one kind of conversation.
    const chips = [
        { key: 'all', label: t('All'), on: !filters.unread && !filters.kind, href: filterUrl({ unread: false, kind: '' }) },
        { key: 'unread', label: t('Unread'), on: filters.unread, href: filterUrl({ unread: true, kind: '' }), count: filters.archived ? 0 : inbox.unread },
        { key: 'hiring', label: t('Hiring'), on: !filters.unread && filters.kind === 'hiring', href: filterUrl({ unread: false, kind: 'hiring' }) },
        { key: 'contracts', label: t('Contracts'), on: !filters.unread && filters.kind === 'contracts', href: filterUrl({ unread: false, kind: 'contracts' }) },
    ];
    const pinned = selected && !conversations.data.some((item) => item.id === selected.id);
    const rows = pinned ? [selected, ...conversations.data] : conversations.data;
    const pagination = [
        { url: conversations.prev_page_url, Icon: PreviousIcon, label: t('Previous') },
        { url: conversations.next_page_url, Icon: NextIcon, label: t('Next') },
    ];
    return (
        <>
            <div className="chat-list-head">
                {filters.archived && (
                    <Link href={filterUrl({ archived: false })} className="chat-icon-button -ms-2" aria-label={t('Back to inbox')}>
                        {ar ? <ArrowRight className="size-5" aria-hidden="true" /> : <ArrowLeft className="size-5" aria-hidden="true" />}
                    </Link>
                )}
                <h2>{filters.archived ? t('Archived conversations') : t('Messages')}</h2>
                <span className="chat-list-total" title={conversations.total === 1 ? t('1 conversation') : t(':count conversations', { count: conversations.total })}>
                    <span aria-hidden="true">{conversations.total}</span>
                    <span className="sr-only">{conversations.total === 1 ? t('1 conversation') : t(':count conversations', { count: conversations.total })}</span>
                </span>
            </div>
            <div className="chat-list-tools">
                <form action="/messages" method="get" role="search" className="chat-search" onSubmit={(event) => { event.preventDefault(); router.get(filterUrl({ q: search.trim() })); }}>
                    {filters.kind && <input type="hidden" name="kind" value={filters.kind} />}
                    {filters.archived && <input type="hidden" name="archived" value="1" />}
                    {filters.unread && <input type="hidden" name="unread" value="1" />}
                    <Search className="size-4 shrink-0" aria-hidden="true" />
                    <label className="sr-only" htmlFor="conversation-search">{t('Search conversations')}</label>
                    <input id="conversation-search" type="search" name="q" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('Search people or projects')} dir="auto" />
                </form>
                <nav className="chat-chips" aria-label={t('Filter conversations')}>
                    {chips.map((chip) => (
                        <Link key={chip.key} href={chip.href} className="chat-chip" aria-current={chip.on ? 'page' : undefined}>
                            {chip.label}
                            {!!chip.count && (
                                <span className="chat-count">
                                    <span aria-hidden="true">{chip.count}</span>
                                    <span className="sr-only">{t(':count unread', { count: chip.count })}</span>
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>
            </div>
            <div className="chat-list-scroll">
                {pinned && <p className="chat-list-note">{t('Current conversation')}</p>}
                {!!rows.length && (
                    <ul className="chat-rows">
                        {rows.map((conversation) => {
                            const unread = conversation.unread > 0;
                            const preview = conversation.preview_hidden
                                ? t('Message hidden by moderation')
                                : conversation.preview_files
                                  ? conversation.preview_files === 1 ? t('Sent a file') : t('Sent :count files', { count: conversation.preview_files })
                                  : conversation.preview;
                            return (
                                <li key={conversation.id}>
                                    <Link href={conversationUrl(conversation.id)} className={cn('chat-row', unread && 'chat-row-unread')} aria-current={selected?.id === conversation.id ? 'page' : undefined}>
                                        <ConversationAvatar name={conversation.counterpart} src={conversation.avatar} />
                                        <span className="chat-row-body">
                                            <span className="chat-row-line">
                                                <bdi className="chat-row-name">{conversation.counterpart}</bdi>
                                                <time dateTime={conversation.updated_at} title={time.full(conversation.updated_at)}>{time.list(conversation.updated_at)}</time>
                                            </span>
                                            <span className="chat-row-line">
                                                <span className={cn('chat-row-preview', conversation.preview_hidden && 'italic')}>
                                                    {conversation.preview_mine && !conversation.preview_hidden && <>{t('You:')} </>}
                                                    <bdi>{preview}</bdi>
                                                </span>
                                                {unread && (
                                                    <span className="chat-count">
                                                        <span aria-hidden="true">{conversation.unread}</span>
                                                        <span className="sr-only">{t(':count unread', { count: conversation.unread })}</span>
                                                    </span>
                                                )}
                                            </span>
                                            <span className="chat-row-line chat-row-project">
                                                <StatusMark status={conversation.status} label={false} />
                                                <bdi>{conversation.project.title}</bdi>
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {!rows.length && (
                    <div className="chat-list-empty">
                        <MessageCircle className="mx-auto mb-3 size-7" aria-hidden="true" />
                        <p className="font-medium">{t('No conversations here yet.')}</p>
                        <p>{filters.q || filters.unread || filters.kind ? t('Try another search or filter.') : t('Clients can start a conversation after receiving a proposal.')}</p>
                    </div>
                )}
                {conversations.last_page > 1 && (
                    <nav className="chat-list-pagination" aria-label={t('Conversation pages')}>
                        <span>{t('Page')} {conversations.current_page} / {conversations.last_page}</span>
                        <div className="flex gap-1">
                            {pagination.map(({ url, Icon, label }) => url ? (
                                <Link key={label} href={url} className="chat-icon-button" aria-label={label}><Icon className="size-4" aria-hidden="true" /></Link>
                            ) : (
                                <span key={label} className="chat-icon-button opacity-40" aria-hidden="true"><Icon className="size-4" /></span>
                            ))}
                        </div>
                    </nav>
                )}
            </div>
            {!filters.archived && (
                <Link href={filterUrl({ archived: true, unread: false, kind: '', q: '' })} className="chat-archived-link">
                    <Archive className="size-4" aria-hidden="true" />
                    {t('Archived conversations')}
                </Link>
            )}
        </>
    );
}
