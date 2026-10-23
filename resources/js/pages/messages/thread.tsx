// Plan 031: shared by the Messages page and the workroom's Messages tab.
// Plan 032 part 13 (option A): bubbles grouped by sender in Instagram's manner, a time between pauses, the line where
// new messages begin, a menu on each message, and a composer that grows as you write.
import { Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Flag,
    History,
    MoreHorizontal,
    Paperclip,
    Pencil,
    X,
} from 'lucide-react';
import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { toast } from 'sonner';
import {
    AttachedFiles,
    FILE_ACCEPT,
    useFileProblem,
} from '@/components/attached-files';
import InputError from '@/components/input-error';
import ReportDialog from '@/components/report-dialog';
import { ConversationAvatar, useChatTime } from '@/components/ui/chat-template';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { FileSize } from '@/pages/discovery/shared';
import type { Page } from '@/pages/discovery/shared';
import type { Message } from './shared';

const MINUTE = 60_000;
// Messages from one side less than ten minutes apart read as one group; an hour's pause brings a time.
const GROUP = 10 * MINUTE;
const PAUSE = 60 * MINUTE;

function MessageItem({
    message,
    counterpart,
    avatar,
    starts,
    ends,
}: {
    message: Message;
    counterpart: string;
    avatar?: string | null;
    starts: boolean;
    ends: boolean;
}) {
    const { t, ar } = useTranslation();
    const time = useChatTime();
    const { demo } = useDemoSession();
    const [editing, setEditing] = useState(false);
    const [history, setHistory] = useState(false);
    const [report, setReport] = useState(false);
    const [tools, setTools] = useState(false);
    const form = useForm({
        body: message.body ?? '',
        version: message.version,
    });
    const left = Math.ceil(
        (new Date(message.created_at).getTime() + 15 * MINUTE - Date.now()) /
            MINUTE,
    );
    const canEdit = message.can_edit && left > 0;
    const actions =
        !message.hidden &&
        (canEdit ||
            !!message.body ||
            message.revisions.length > 0 ||
            (!message.mine && !demo));
    const who = message.mine ? t('You') : counterpart;
    // Q68: a message moderation hides while it is being corrected leaves the form at once.
    const correcting = editing && !message.hidden;
    return (
        <div
            className={cn(
                'chat-message',
                message.mine && 'chat-message-mine',
                starts && 'chat-message-starts',
                ends && 'chat-message-ends',
                tools && 'chat-message-open',
            )}
            // A touch screen has no hover: a tap on a message shows its menu and time.
            onClick={(event) => {
                if (
                    !(event.target as HTMLElement).closest(
                        'a, button, form, [role="menu"]',
                    )
                )
                    setTools((open) => !open);
            }}
        >
            {!message.mine && (
                <span className="chat-message-face">
                    {ends && (
                        <ConversationAvatar
                            name={counterpart}
                            src={avatar}
                            size={28}
                        />
                    )}
                </span>
            )}
            <article
                className="chat-message-column"
                aria-label={`${who}, ${time.full(message.created_at)}`}
            >
                {correcting ? (
                    <form
                        className="chat-edit"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch('/messages/items/' + message.id, {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            });
                        }}
                    >
                        <label
                            className="text-sm font-medium"
                            htmlFor={'correction-' + message.id}
                        >
                            {t('Correct message')}
                        </label>
                        <textarea
                            id={'correction-' + message.id}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            required
                            maxLength={10000}
                            rows={3}
                            autoFocus
                            dir="auto"
                            aria-invalid={!!form.errors.body}
                        />
                        <InputError message={form.errors.body} />
                        <div className="flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                className="chat-pill-button"
                                disabled={form.processing}
                                onClick={() => setEditing(false)}
                            >
                                {t('Cancel')}
                            </button>
                            <button
                                type="submit"
                                className="chat-pill-button chat-pill-button-primary"
                                disabled={
                                    form.processing || !form.data.body.trim()
                                }
                            >
                                {t('Save correction')}
                            </button>
                        </div>
                    </form>
                ) : (
                    <div
                        className={cn(
                            'chat-bubble',
                            message.hidden && 'chat-bubble-hidden',
                        )}
                        title={time.full(message.created_at)}
                    >
                        {/* Q68: a notice stands in for a message that moderation hid. */}
                        {message.hidden ? (
                            <p>{t('This message was hidden by moderation.')}</p>
                        ) : (
                            <>
                                {!!message.body && (
                                    <p dir="auto">{message.body}</p>
                                )}
                                <AttachedFiles
                                    files={message.files}
                                    href={(file) =>
                                        `/messages/files/${file.id}`
                                    }
                                />
                            </>
                        )}
                    </div>
                )}
                {message.edited_at && !correcting && (
                    <p className="chat-message-edited">{t('Edited')}</p>
                )}
            </article>
            {!correcting && (
                <span className="chat-message-tools">
                    {actions && (
                        <DropdownMenu dir={ar ? 'rtl' : 'ltr'} modal={false}>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="chat-message-more"
                                    aria-label={t('Message actions')}
                                >
                                    <MoreHorizontal
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align={message.mine ? 'end' : 'start'}
                                className="min-w-56"
                            >
                                {canEdit && (
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            form.setData({
                                                body: message.body ?? '',
                                                version: message.version,
                                            });
                                            setEditing(true);
                                        }}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t('Correct message')}
                                        <span className="text-muted-foreground ms-auto text-xs">
                                            {t(':count min left', {
                                                count: left,
                                            })}
                                        </span>
                                    </DropdownMenuItem>
                                )}
                                {!!message.body && (
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            navigator.clipboard
                                                ?.writeText(message.body ?? '')
                                                .then(
                                                    () =>
                                                        toast(t('Text copied')),
                                                    () => {},
                                                );
                                        }}
                                    >
                                        <Copy aria-hidden="true" />
                                        {t('Copy text')}
                                    </DropdownMenuItem>
                                )}
                                {!!message.revisions.length && (
                                    <DropdownMenuItem
                                        onSelect={() => setHistory(true)}
                                    >
                                        <History aria-hidden="true" />
                                        {t('Correction history')}
                                    </DropdownMenuItem>
                                )}
                                {/* Plan 032 part 10: a shared demo account sends no reports. */}
                                {!message.mine && !demo && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() => setReport(true)}
                                        >
                                            <Flag aria-hidden="true" />
                                            {t('Report message')}
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                    <time dateTime={message.created_at}>
                        {time.clock(message.created_at)}
                    </time>
                </span>
            )}
            {!!message.revisions.length && (
                <Dialog open={history} onOpenChange={setHistory}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Correction history')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'Earlier versions of this message, oldest first.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <ol className="chat-revisions">
                            {message.revisions.map((revision) => (
                                <li key={revision.version}>
                                    <p dir="auto">{revision.body}</p>
                                    <time dateTime={revision.created_at}>
                                        {t('Replaced :when', {
                                            when: time.full(
                                                revision.created_at,
                                            ),
                                        })}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    </DialogContent>
                </Dialog>
            )}
            {!message.mine && (
                <ReportDialog
                    type="message"
                    id={message.id}
                    label={t('Report message')}
                    open={report}
                    onOpenChange={setReport}
                />
            )}
        </div>
    );
}

/**
 * The scrolling history: the newest message at the bottom and in view, the line where new messages begin, and a
 * button back to the latest when the reader has scrolled up and something arrives.
 */
export function MessageHistory({
    conversationId,
    messages,
    counterpart,
    avatar,
    busy,
    firstUnread = null,
    newMessages,
    start,
}: {
    conversationId: number;
    messages: Page<Message>;
    counterpart: string;
    avatar?: string | null;
    busy: boolean;
    // The first message that was unread when the conversation opened.
    firstUnread?: number | null;
    // How many of the other side's messages were new then, counted on the server across every page.
    newMessages?: number;
    // Shown above the first message once the oldest page is on screen.
    start?: ReactNode;
}) {
    const { t } = useTranslation();
    const time = useChatTime();
    const scroller = useRef<HTMLDivElement>(null);
    const divider = useRef<HTMLDivElement>(null);
    // Decided when the conversation opens, so the line stays where it was while the reader catches up and pages back.
    // It moves only when messages that arrived in a hidden browser tab are read on coming back: a new starting point
    // on the same page, not one that comes with paging back to the newest page.
    const count = (from: number | null) =>
        from === null
            ? 0
            : messages.data.filter(
                  (message) => message.id >= from && !message.mine,
              ).length;
    const [cut, setCut] = useState(firstUnread);
    const [fresh, setFresh] = useState(() => newMessages || count(firstUnread));
    const [given, setGiven] = useState({
        first: firstUnread,
        page: messages.current_page,
    });
    if (firstUnread !== given.first || messages.current_page !== given.page) {
        setGiven({ first: firstUnread, page: messages.current_page });
        if (
            firstUnread !== null &&
            firstUnread !== given.first &&
            messages.current_page === given.page
        ) {
            setCut(firstUnread);
            setFresh(newMessages || count(firstUnread));
        }
    }
    const bottom = useRef(true);
    const [away, setAway] = useState(false);
    const [missed, setMissed] = useState(0);
    const seen = useRef<number | undefined>(undefined);
    const list = [...messages.data].reverse();
    // The line goes before the first new message; when that one is on an older page, at the top of the newest page.
    const lineAt =
        cut === null
            ? undefined
            : list.some((message) => message.id === cut)
              ? cut
              : messages.current_page === 1 && list.length && cut < list[0].id
                ? list.find((message) => !message.mine)?.id
                : undefined;
    const latest = messages.data[0];
    const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // A page opens on the line where new messages begin when it would be out of view, otherwise on the newest.
    useLayoutEffect(() => {
        const box = scroller.current;
        if (!box) return;
        const line = divider.current;
        box.scrollTop =
            line && line.offsetTop + 80 > box.clientHeight
                ? line.offsetTop - 24
                : box.scrollHeight;
        // 0 for a conversation with no message yet, so its first message is still followed.
        seen.current = latest?.id ?? 0;
        setMissed(0);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only a new conversation or page moves the reader
    }, [conversationId, messages.current_page]);

    // Something new: follow it when the reader is at the bottom or sent it; otherwise say so on the button.
    useLayoutEffect(() => {
        if (seen.current === undefined || latest?.id === seen.current) return;
        const last = seen.current ?? 0;
        seen.current = latest?.id;
        const box = scroller.current;
        if (!box) return;
        if (bottom.current || latest?.mine) {
            box.scrollTo({
                top: box.scrollHeight,
                behavior: reduce ? 'auto' : 'smooth',
            });
            setMissed(0);
        } else {
            // When the last seen message is no longer on the page, more arrived than one page holds: no number then.
            const oldest = messages.data[messages.data.length - 1]?.id ?? 0;
            const arrived = messages.data.filter(
                (message) => message.id > last && !message.mine,
            ).length;
            setMissed((count) =>
                count < 0 || last < oldest ? -1 : count + arrived,
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only a newer message moves the reader
    }, [latest?.id]);

    useEffect(() => {
        const box = scroller.current;
        if (!box) return;
        const check = () => {
            const near =
                box.scrollHeight - box.scrollTop - box.clientHeight < 120;
            bottom.current = near;
            setAway(!near);
            if (near) setMissed(0);
        };
        check();
        box.addEventListener('scroll', check, { passive: true });
        // The details opening, a longer draft or a turned phone resize the history: a reader at the bottom stays there.
        const resize = new ResizeObserver(() => {
            if (bottom.current) box.scrollTop = box.scrollHeight;
        });
        resize.observe(box);
        return () => {
            box.removeEventListener('scroll', check);
            resize.disconnect();
        };
    }, []);

    let previous: Message | undefined;
    const items: ReactNode[] = [];
    list.forEach((message, i) => {
        const next = list[i + 1];
        const at = new Date(message.created_at).getTime();
        const before = previous
            ? at - new Date(previous.created_at).getTime()
            : Infinity;
        const gap =
            !previous ||
            before > PAUSE ||
            !time.sameDay(previous.created_at, message.created_at);
        const isCut = message.id === lineAt;
        const nextAt = next ? new Date(next.created_at).getTime() : 0;
        const starts =
            gap || isCut || previous?.mine !== message.mine || before > GROUP;
        const ends =
            !next ||
            next.mine !== message.mine ||
            nextAt - at > GROUP ||
            !time.sameDay(message.created_at, next.created_at) ||
            next.id === lineAt;
        if (isCut)
            items.push(
                <div
                    key="new"
                    ref={divider}
                    className="chat-new-line"
                    role="separator"
                >
                    {fresh === 1
                        ? t('1 new message')
                        : t(':count new messages', { count: fresh })}
                </div>,
            );
        else if (gap)
            items.push(
                <p key={'t' + message.id} className="chat-time">
                    <time dateTime={message.created_at}>
                        {time.stamp(message.created_at)}
                    </time>
                </p>,
            );
        items.push(
            <MessageItem
                key={message.id}
                message={message}
                counterpart={counterpart}
                avatar={avatar}
                starts={starts}
                ends={ends}
            />,
        );
        previous = message;
    });

    return (
        <div className="chat-history">
            <div
                ref={scroller}
                className="chat-thread"
                tabIndex={0}
                role="region"
                aria-label={t('Message history')}
                aria-busy={busy}
            >
                {messages.next_page_url ? (
                    <div className="chat-older">
                        <Link
                            href={messages.next_page_url}
                            preserveScroll
                            preserveState
                            className="chat-pill-button"
                        >
                            {t('Show older messages')}
                        </Link>
                    </div>
                ) : (
                    start
                )}
                {items}
                {!!messages.prev_page_url && (
                    <div className="chat-older">
                        <Link
                            href={messages.prev_page_url}
                            preserveScroll
                            preserveState
                            className="chat-pill-button"
                        >
                            {t('Show newer messages')}
                        </Link>
                    </div>
                )}
            </div>
            {messages.current_page === 1 && (
                <button
                    type="button"
                    className={cn('chat-jump', away && 'chat-jump-on')}
                    tabIndex={away ? 0 : -1}
                    aria-hidden={!away}
                    onClick={() =>
                        scroller.current?.scrollTo({
                            top: scroller.current.scrollHeight,
                            behavior: reduce ? 'auto' : 'smooth',
                        })
                    }
                >
                    <ArrowDown className="size-4" aria-hidden="true" />
                    {missed < 0
                        ? t('New messages')
                        : missed > 0
                          ? t(':count new', { count: missed })
                          : t('Latest')}
                </button>
            )}
        </div>
    );
}

/** The composer, or the read-only notice. `extra` travels with every send (the workroom asks to stay in place). */
export function MessageComposer({
    url,
    writable,
    fileLimit,
    extra = {},
}: {
    url: string;
    writable: boolean;
    fileLimit: number;
    extra?: Record<string, boolean>;
}) {
    const { t } = useTranslation();
    // Plan 032 part 10: no file enters storage through the shared demo account.
    const { demo } = useDemoSession();
    const body = useRef<HTMLTextAreaElement>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const form = useForm<{
        body: string;
        client_token: string;
        files: File[];
    }>({
        body: '',
        client_token: crypto.randomUUID(),
        files: [],
    });
    // Plan 030: files chosen for the next message, checked here first and again on the server.
    const fileProblem = useFileProblem();
    const [fileNote, setFileNote] = useState<string | null>(null);
    // On a touch screen Enter starts a new line, as the keyboard's own key suggests.
    const [touch] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(hover: none)').matches,
    );
    const fileError =
        form.errors.files ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('files.'),
        )?.[1];
    const empty = !form.data.body.trim() && !form.data.files.length;
    function grow() {
        const box = body.current;
        if (!box) return;
        box.style.height = 'auto';
        box.style.height = box.scrollHeight + 'px';
    }
    useLayoutEffect(grow, [form.data.body]);
    function choose(chosen: FileList | null) {
        const picked = Array.from(chosen ?? []);
        if (!picked.length) return;
        const problem = picked.map(fileProblem).find(Boolean);
        if (problem) return setFileNote(problem);
        if (form.data.files.length + picked.length > fileLimit)
            return setFileNote(
                t('A message can carry up to :count files.', {
                    count: fileLimit,
                }),
            );
        setFileNote(null);
        form.clearErrors();
        form.setData('files', [...form.data.files, ...picked]);
    }
    if (!writable)
        return (
            <p role="status" className="chat-readonly">
                {t('This hiring conversation is read-only.')}
            </p>
        );
    const fileRule = t(
        'Attach up to :count files of 5 MB each: JPG, PNG, WebP, PDF, TXT or ZIP. Files cannot be removed once sent.',
        { count: fileLimit },
    );
    return (
        <form
            ref={formRef}
            className="chat-composer"
            onSubmit={(event) => {
                event.preventDefault();
                if (empty || form.processing) return;
                form.transform((data) => ({ ...data, ...extra }));
                form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.setData({
                            body: '',
                            client_token: crypto.randomUUID(),
                            files: [],
                        });
                        setFileNote(null);
                        body.current?.focus();
                    },
                });
            }}
        >
            {!!form.data.files.length && (
                <ul
                    className="chat-pending-files"
                    aria-label={t('Files to send')}
                >
                    {form.data.files.map((file, i) => (
                        <li key={i}>
                            <Paperclip size={14} aria-hidden="true" />
                            <bdi>{file.name}</bdi>
                            <span className="attached-files-size">
                                <FileSize bytes={file.size} />
                            </span>
                            <button
                                type="button"
                                disabled={form.processing}
                                aria-label={t('Remove :name', {
                                    name: file.name,
                                })}
                                onClick={() =>
                                    form.setData(
                                        'files',
                                        form.data.files.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <div className="chat-pill">
                {!demo && (
                    <>
                        <input
                            id="message-files"
                            type="file"
                            multiple
                            className="sr-only"
                            accept={FILE_ACCEPT}
                            disabled={
                                form.processing ||
                                form.data.files.length >= fileLimit
                            }
                            aria-describedby="message-file-rule"
                            onChange={(event) => {
                                choose(event.target.files);
                                event.target.value = '';
                            }}
                        />
                        <label
                            htmlFor="message-files"
                            className="chat-attach"
                            title={fileRule}
                        >
                            <Paperclip className="size-5" aria-hidden="true" />
                            <span className="sr-only">{t('Attach files')}</span>
                        </label>
                        <span id="message-file-rule" className="sr-only">
                            {fileRule}
                        </span>
                    </>
                )}
                <label htmlFor="message-body" className="sr-only">
                    {t('Message')}
                </label>
                <textarea
                    ref={body}
                    id="message-body"
                    rows={1}
                    required={!form.data.files.length}
                    maxLength={10000}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter' &&
                            !event.shiftKey &&
                            !touch &&
                            !event.nativeEvent.isComposing
                        ) {
                            event.preventDefault();
                            formRef.current?.requestSubmit();
                        }
                    }}
                    placeholder={t('Message…')}
                    aria-describedby="message-help"
                    aria-invalid={!!form.errors.body}
                    readOnly={form.processing}
                    dir="auto"
                />
                <button
                    type="submit"
                    className="chat-send"
                    disabled={form.processing || empty}
                    aria-label={
                        form.processing ? t('Sending...') : t('Send message')
                    }
                >
                    <ArrowUp className="size-5" aria-hidden="true" />
                </button>
            </div>
            <InputError message={form.errors.body} />
            <InputError message={fileNote ?? fileError} />
            <p id="message-help" className="chat-hint">
                {!touch && (
                    <>{t('Enter sends · Shift + Enter for a new line')} · </>
                )}
                {t(
                    'You can correct sent text for 15 minutes. Messages cannot be deleted.',
                )}
                {demo && (
                    <> {t('Files are switched off in the shared demo.')}</>
                )}
            </p>
        </form>
    );
}
