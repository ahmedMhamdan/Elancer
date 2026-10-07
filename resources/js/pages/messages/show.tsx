// Compose/correction controls reuse the inspected local TailAdmin TextArea/Button sources.
import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    CheckCheck,
    MoreHorizontal,
    RefreshCw,
    Send,
    ShieldBan,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import ReportDialog from '@/components/report-dialog';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import TextArea from '@/components/tailadmin/textarea';
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
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Page } from '@/pages/discovery/shared';
import {
    MessageLayout,
    type ConversationSummary,
    type Message,
} from './shared';

function MessageItem({
    message,
    counterpart,
}: {
    message: Message;
    counterpart: string;
}) {
    const { t, locale } = useTranslation();
    const [editing, setEditing] = useState(false);
    const form = useForm({
        body: message.body ?? '',
        version: message.version,
    });
    const sent = (
        <div className="chat-message-meta">
            <span>{message.mine ? t('You') : counterpart}</span>
            <time dateTime={message.created_at}>
                {new Date(message.created_at).toLocaleString(locale, {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                })}
            </time>
            {message.edited_at && <span>{t('Edited')}</span>}
        </div>
    );
    // Q68: a notice stands in for a message that moderation hid, with nothing to correct or report.
    if (message.hidden)
        return (
            <article
                className={cn(
                    'chat-message',
                    message.mine && 'chat-message-mine',
                )}
                aria-label={message.mine ? t('You') : counterpart}
            >
                <div className="chat-bubble">
                    <p className="text-sm leading-7 italic">
                        {t('This message was hidden by moderation.')}
                    </p>
                    {sent}
                </div>
            </article>
        );
    return (
        <article
            className={cn('chat-message', message.mine && 'chat-message-mine')}
            aria-label={message.mine ? t('You') : counterpart}
        >
            <div className="chat-bubble">
                {editing ? (
                    <form
                        className="chat-edit space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch('/messages/items/' + message.id, {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            });
                        }}
                    >
                        <label
                            className="block text-sm font-medium"
                            htmlFor={'correction-' + message.id}
                        >
                            {t('Correct message')}
                        </label>
                        <TextArea
                            id={'correction-' + message.id}
                            value={form.data.body}
                            onChange={(value) => form.setData('body', value)}
                            required
                            maxLength={10000}
                            autoFocus
                            aria-invalid={!!form.errors.body}
                        />
                        <InputError message={form.errors.body} />
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing || !form.data.body.trim()
                                }
                            >
                                {t('Save correction')}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setEditing(false)}
                            >
                                {t('Cancel')}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p
                        className="text-sm leading-7 whitespace-pre-wrap"
                        dir="auto"
                    >
                        {message.body}
                    </p>
                )}
                {sent}
                {(message.can_edit ||
                    !message.mine ||
                    message.revisions.length > 0) && (
                    <div className="chat-message-actions">
                        {!message.mine && (
                            <ReportDialog
                                type="message"
                                id={message.id}
                                label={t('Report message')}
                                className="!text-xs"
                            />
                        )}
                        {message.can_edit && !editing && (
                            <button
                                type="button"
                                onClick={() => {
                                    form.setData({
                                        body: message.body ?? '',
                                        version: message.version,
                                    });
                                    setEditing(true);
                                }}
                            >
                                {t('Correct message')}
                            </button>
                        )}
                        {!!message.revisions.length && (
                            <details>
                                <summary>{t('Correction history')}</summary>
                                <ol className="mt-2 space-y-3 border-t border-current/20 pt-3">
                                    {message.revisions.map((revision) => (
                                        <li key={revision.version}>
                                            <p
                                                className="text-sm leading-relaxed whitespace-pre-wrap"
                                                dir="auto"
                                            >
                                                {revision.body}
                                            </p>
                                            <time
                                                dateTime={revision.created_at}
                                            >
                                                {new Date(
                                                    revision.created_at,
                                                ).toLocaleString(locale, {
                                                    timeZoneName: 'short',
                                                })}
                                            </time>
                                        </li>
                                    ))}
                                </ol>
                            </details>
                        )}
                    </div>
                )}
            </div>
        </article>
    );
}

type ShowProps = {
    conversation: ConversationSummary;
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
    messages: Page<Message>;
    writable: boolean;
    visibleThrough: number;
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
}: ShowProps) {
    const { t, ar } = useTranslation();
    const page = usePage();
    const form = useForm({ body: '', client_token: crypto.randomUUID() });
    const [refreshing, setRefreshing] = useState(false);
    const thread = useRef<HTMLDivElement>(null);
    const latestId = messages.data[0]?.id;
    useEffect(() => {
        if (thread.current)
            thread.current.scrollTop = thread.current.scrollHeight;
    }, [conversation.id, messages.current_page, latestId]);
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
                    <DropdownMenuSeparator />
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
                                    '/messages/' + conversation.id + '/block',
                                );
                        }}
                    >
                        <ShieldBan aria-hidden="true" />
                        {t('Block account')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
    const composer = writable ? (
        <form
            className="chat-composer"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(page.url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.setData({
                            body: '',
                            client_token: crypto.randomUUID(),
                        });
                        document.getElementById('message-body')?.focus();
                    },
                });
            }}
        >
            <label htmlFor="message-body" className="sr-only">
                {t('Message')}
            </label>
            <div className="flex items-end gap-2">
                <div className="min-w-0 flex-1">
                    <TextArea
                        id="message-body"
                        rows={2}
                        required
                        maxLength={10000}
                        value={form.data.body}
                        onChange={(value) => form.setData('body', value)}
                        placeholder={t('Type a message')}
                        aria-describedby="message-help"
                        aria-invalid={!!form.errors.body}
                        disabled={form.processing}
                        dir="auto"
                    />
                </div>
                <Button
                    type="submit"
                    className="mb-1 min-h-11"
                    disabled={form.processing || !form.data.body.trim()}
                    startIcon={
                        <Send
                            className="size-4 rtl:rotate-180"
                            aria-hidden="true"
                        />
                    }
                >
                    <span className="sr-only sm:not-sr-only">
                        {form.processing ? t('Sending...') : t('Send message')}
                    </span>
                </Button>
            </div>
            <InputError message={form.errors.body} />
            <p
                id="message-help"
                className="text-muted-foreground mt-2 text-xs leading-relaxed"
            >
                {t(
                    'You can correct sent text for 15 minutes. Messages cannot be deleted.',
                )}
            </p>
        </form>
    ) : (
        <p role="status" className="chat-readonly">
            {t('This hiring conversation is read-only.')}
        </p>
    );
    return (
        <ChatTemplate
            conversations={conversations}
            filters={filters}
            selected={conversation}
            header={header}
            composer={composer}
        >
            <div
                ref={thread}
                className="chat-thread"
                tabIndex={0}
                role="region"
                aria-label={t('Message history')}
                aria-busy={refreshing}
            >
                {messages.last_page > 1 && (
                    <div className="mb-5">
                        <Pagination data={messages} />
                    </div>
                )}
                {[...messages.data].reverse().map((message) => (
                    <MessageItem
                        key={message.id}
                        message={message}
                        counterpart={conversation.counterpart}
                    />
                ))}
            </div>
        </ChatTemplate>
    );
}
