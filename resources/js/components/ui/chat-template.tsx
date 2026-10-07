// Adapted from Ahmed's supplied 21st.dev Chat template by Manoj Rayi (MIT).
// Keeps its contact-list/chat-window composition inside Elancer's existing sidebar.
// Local TailAdmin InputField/TextArea/Button sources were inspected for form controls.
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ChevronLeft, ChevronRight, ListFilter, MessageCircle, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
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

function queryFor(filters: ConversationFilters) {
    const query = new URLSearchParams();
    if (filters.kind) query.set('kind', filters.kind);
    if (filters.archived) query.set('archived', '1');
    if (filters.unread) query.set('unread', '1');
    if (filters.q) query.set('q', filters.q);
    return query;
}

export function ConversationAvatar({ name }: { name: string }) {
    const initials = name.trim().split(/\s+/u).slice(0, 2).map((word) => Array.from(word)[0]).join('');
    return (
        <Avatar className="size-11" aria-hidden="true">
            <AvatarFallback className="bg-primary/10 text-primary text-sm font-semibold">{initials}</AvatarFallback>
        </Avatar>
    );
}

export function ChatTemplate({
    conversations, filters, selected, header, children, composer,
}: {
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    selected?: ConversationSummary;
    header?: ReactNode;
    children?: ReactNode;
    composer?: ReactNode;
}) {
    const { t, ar } = useTranslation();
    const page = usePage();
    const BackIcon = ar ? ArrowRight : ArrowLeft;
    const query = queryFor(filters);
    const listPage = new URLSearchParams(page.url.split('?')[1] ?? '').get('list_page');
    if (selected && listPage) query.set('page', listPage);
    const inboxUrl = '/messages' + (query.size ? '?' + query.toString() : '');
    return (
        <div className="chat-template">
            <aside className={cn('chat-list', selected && 'chat-list-selected')} aria-label={t('Conversations')}>
                <ConversationList key={JSON.stringify(filters)} conversations={conversations} filters={filters} selected={selected} />
            </aside>
            <section className={cn('chat-window', !selected && 'chat-window-empty')} aria-label={t('Messages')}>
                {selected ? (
                    <>
                        <header className="chat-header">
                            <Link href={inboxUrl} className="chat-back chat-icon-button" aria-label={t('Back to conversations')}>
                                <BackIcon className="size-5" aria-hidden="true" />
                            </Link>
                            <ConversationAvatar name={selected.counterpart} />
                            <div className="min-w-0 flex-1">
                                <h2 className="truncate font-semibold" dir="auto">{selected.counterpart}</h2>
                                <p className="text-muted-foreground truncate text-sm" dir="auto">{selected.project.title}</p>
                            </div>
                            {header}
                        </header>
                        {children}
                        {composer}
                    </>
                ) : (
                    <div className="chat-empty">
                        <span className="bg-primary/10 text-primary grid size-16 place-items-center rounded-2xl">
                            <MessageCircle className="size-7" aria-hidden="true" />
                        </span>
                        <h2 className="text-xl font-semibold">{t('Select a conversation')}</h2>
                        <p className="text-muted-foreground max-w-xs text-sm leading-relaxed">{t('Keep project discussions and agreements together. Choose a conversation to read and reply.')}</p>
                    </div>
                )}
            </section>
        </div>
    );
}

function ConversationList({ conversations, filters, selected }: {
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    selected?: ConversationSummary;
}) {
    const { t, locale, ar } = useTranslation();
    const [search, setSearch] = useState(filters.q);
    const PreviousIcon = ar ? ChevronRight : ChevronLeft;
    const NextIcon = ar ? ChevronLeft : ChevronRight;
    const selections = [
        { label: t('Inbox'), kind: '' as const, archived: false },
        { label: t('Hiring'), kind: 'hiring' as const, archived: false },
        { label: t('Contracts'), kind: 'contracts' as const, archived: false },
        { label: t('Archived conversations'), kind: '' as const, archived: true },
    ];
    function filterUrl(changes: Partial<ConversationFilters>) {
        const query = queryFor({ ...filters, ...changes });
        return '/messages' + (query.size ? '?' + query.toString() : '');
    }
    function conversationUrl(id: number) {
        const query = queryFor(filters);
        if (conversations.current_page > 1) query.set('list_page', String(conversations.current_page));
        return '/messages/' + id + (query.size ? '?' + query.toString() : '');
    }
    const pagination = [
        { url: conversations.prev_page_url, Icon: PreviousIcon, label: t('Previous') },
        { url: conversations.next_page_url, Icon: NextIcon, label: t('Next') },
    ];
    const heading = filters.archived ? t('Archived conversations') : filters.kind === 'contracts' ? t('Contracts') : filters.kind === 'hiring' ? t('Hiring') : t('Inbox');
    const rows = selected && !conversations.data.some((item) => item.id === selected.id)
        ? [selected, ...conversations.data]
        : conversations.data;
    return (
        <>
            <div className="chat-list-tools">
                <div className="flex items-center justify-between gap-2">
                    <div>
                        <h2 className="font-semibold">{heading}</h2>
                        <p className="text-muted-foreground mt-1 text-xs">{t(':count conversations', { count: conversations.total })}</p>
                    </div>
                    <DropdownMenu dir={ar ? 'rtl' : 'ltr'}>
                        <DropdownMenuTrigger asChild>
                            <button type="button" className="chat-icon-button" aria-label={t('Filter conversations')}>
                                <ListFilter className="size-5" aria-hidden="true" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {selections.map((item) => (
                                <DropdownMenuItem key={item.label} asChild>
                                    <Link href={filterUrl({ kind: item.kind, archived: item.archived })}>{item.label}</Link>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <form action="/messages" method="get" className="chat-search" onSubmit={(event) => { event.preventDefault(); router.get(filterUrl({ q: search })); }}>
                    {filters.kind && <input type="hidden" name="kind" value={filters.kind} />}
                    {filters.archived && <input type="hidden" name="archived" value="1" />}
                    {filters.unread && <input type="hidden" name="unread" value="1" />}
                    <label className="sr-only" htmlFor="conversation-search">{t('Search conversations')}</label>
                    <Input id="conversation-search" type="search" name="q" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('Search people or projects')} className="pe-12" />
                    <button type="submit" className="chat-icon-button absolute inset-y-0 end-0" aria-label={t('Search conversations')}>
                        <Search className="size-4" aria-hidden="true" />
                    </button>
                </form>
                <nav className="flex gap-2" aria-label={t('Filter conversations')}>
                    <Link href={filterUrl({ unread: false })} className={cn('chat-filter', !filters.unread && 'chat-filter-active')} aria-current={!filters.unread ? 'page' : undefined}>{t('All')}</Link>
                    <Link href={filterUrl({ unread: true })} className={cn('chat-filter', filters.unread && 'chat-filter-active')} aria-current={filters.unread ? 'page' : undefined}>{t('Unread')}</Link>
                </nav>
            </div>
            <div className="chat-list-scroll">
                {selected && !conversations.data.some((item) => item.id === selected.id) && <p className="text-muted-foreground px-4 py-2 text-xs">{t('Current conversation')}</p>}
                {rows.map((conversation) => (
                    <Link key={conversation.id} href={conversationUrl(conversation.id)} className={cn('chat-contact', selected?.id === conversation.id && 'chat-contact-active')} aria-current={selected?.id === conversation.id ? 'page' : undefined}>
                        <ConversationAvatar name={conversation.counterpart} />
                        <div className="min-w-0 flex-1">
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="min-w-0 flex-1 truncate text-sm font-semibold" dir="auto">{conversation.counterpart}</span>
                                <time dateTime={conversation.updated_at} className="text-muted-foreground shrink-0 text-xs">{new Date(conversation.updated_at).toLocaleDateString(locale, { month: 'short', day: 'numeric' })}</time>
                            </div>
                            <p className="text-muted-foreground mt-1 truncate text-xs" dir="auto">{conversation.project.title}</p>
                            <div className="mt-2 flex items-center gap-2">
                                <p className="text-muted-foreground min-w-0 flex-1 truncate text-sm" dir="auto">{conversation.preview_hidden ? t('Message hidden by moderation') : conversation.preview}</p>
                                {conversation.unread > 0 && <span className="bg-primary text-primary-foreground min-w-5 rounded-full px-1.5 py-0.5 text-center text-xs font-semibold"><span aria-hidden="true">{conversation.unread}</span><span className="sr-only">{t(':count unread', { count: conversation.unread })}</span></span>}
                            </div>
                        </div>
                    </Link>
                ))}
                {!rows.length && (
                    <div className="chat-list-empty">
                        <MessageCircle className="text-muted-foreground mx-auto mb-3 size-7" aria-hidden="true" />
                        <p className="font-medium">{t('No conversations here yet.')}</p>
                        <p className="text-muted-foreground mt-2 text-sm">{filters.q || filters.unread ? t('Try another search or filter.') : t('Clients can start a conversation after receiving a proposal.')}</p>
                    </div>
                )}
            </div>
            {conversations.last_page > 1 && (
                <nav className="chat-list-pagination" aria-label={t('Conversation pages')}>
                    <span className="text-muted-foreground text-xs">{t('Page')} {conversations.current_page} / {conversations.last_page}</span>
                    <div className="flex gap-1">
                        {pagination.map(({ url, Icon, label }) => url ? (
                            <Link key={label} href={url} className="chat-icon-button" aria-label={label}><Icon className="size-4" aria-hidden="true" /></Link>
                        ) : (
                            <Button key={label} variant="outline" disabled aria-label={label}><Icon className="size-4" aria-hidden="true" /></Button>
                        ))}
                    </div>
                </nav>
            )}
        </>
    );
}
