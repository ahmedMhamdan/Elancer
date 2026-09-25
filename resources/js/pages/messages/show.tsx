// Compose controls reuse local TailAdmin form/form-elements/TextAreaInput.tsx and existing adapted buttons.
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import InputError from '@/components/input-error';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { type Page } from '@/pages/discovery/shared';
import {
    MessageLayout,
    type ConversationSummary,
    type Message,
} from './shared';

function MessageItem({ message }: { message: Message }) {
    const { t, locale } = useTranslation();
    const [editing, setEditing] = useState(false);
    const form = useForm({ body: message.body, version: message.version });
    return (
        <article className="market-panel market-stack">
            <div className="market-actions">
                <strong>{message.mine ? t('You') : t('Counterpart')}</strong>
                <time dateTime={message.created_at}>
                    {new Date(message.created_at).toLocaleString(locale, {
                        timeZoneName: 'short',
                    })}
                </time>
                {message.edited_at && <span>{t('Edited')}</span>}
            </div>
            {editing ? (
                <form
                    className="market-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(`/messages/items/${message.id}`, {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        });
                    }}
                >
                    <div className="market-field">
                        <label htmlFor={`correction-${message.id}`}>
                            {t('Correct message')}
                        </label>
                        <TextArea
                            id={`correction-${message.id}`}
                            value={form.data.body}
                            onChange={(value) => form.setData('body', value)}
                            required
                            maxLength={10000}
                        />
                    </div>
                    <InputError message={form.errors.body} />
                    <div className="market-actions">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save correction')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setEditing(false)}
                        >
                            {t('Cancel')}
                        </Button>
                    </div>
                </form>
            ) : (
                <p className="market-prose break-words" dir="auto">
                    {message.body}
                </p>
            )}
            {message.can_edit && !editing && (
                <div>
                    <Button
                        variant="outline"
                        onClick={() => {
                            form.setData({
                                body: message.body,
                                version: message.version,
                            });
                            setEditing(true);
                        }}
                    >
                        {t('Correct message')}
                    </Button>
                </div>
            )}
            {!!message.revisions.length && (
                <details>
                    <summary className="cursor-pointer">
                        {t('Correction history')}
                    </summary>
                    <ol className="market-stack mt-3">
                        {message.revisions.map((revision) => (
                            <li key={revision.version}>
                                <p
                                    className="market-prose break-words"
                                    dir="auto"
                                >
                                    {revision.body}
                                </p>
                                <time dateTime={revision.created_at}>
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
        </article>
    );
}

export default function Show({
    conversation,
    messages,
    writable,
    visibleThrough,
}: {
    conversation: ConversationSummary;
    messages: Page<Message>;
    writable: boolean;
    visibleThrough: number;
}) {
    const { t } = useTranslation();
    const form = useForm({ body: '', client_token: crypto.randomUUID() });
    return (
        <MessageLayout>
            <section className="market-panel market-stack">
                <h2 dir="auto">{conversation.project.title}</h2>
                <p dir="auto">{conversation.counterpart}</p>
                <div className="market-actions">
                    <Link href={`/proposals/${conversation.proposal_id}`}>
                        {t('View proposal')}
                    </Link>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.reload({
                                only: [
                                    'messages',
                                    'conversation',
                                    'writable',
                                    'visibleThrough',
                                ],
                            })
                        }
                    >
                        {t('Refresh messages')}
                    </Button>
                    {conversation.unread > 0 && visibleThrough > 0 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    `/messages/${conversation.id}/state`,
                                    { read_through: visibleThrough },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('Mark conversation as read')}
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.patch(
                                `/messages/${conversation.id}/state`,
                                { archived: !conversation.archived },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {conversation.archived
                            ? t('Unarchive')
                            : t('Archive conversation')}
                    </Button>
                    <Button
                        variant="danger-outline"
                        onClick={() => {
                            if (
                                window.confirm(
                                    t(
                                        'Block this account? New invitations and proposal contact will be stopped.',
                                    ),
                                )
                            )
                                router.post(
                                    `/messages/${conversation.id}/block`,
                                );
                        }}
                    >
                        {t('Block account')}
                    </Button>
                </div>
            </section>
            {!writable && (
                <p role="status" className="market-panel">
                    {t('This hiring conversation is read-only.')}
                </p>
            )}
            <div className="market-stack">
                {[...messages.data].reverse().map((message) => (
                    <MessageItem key={message.id} message={message} />
                ))}
            </div>
            <Pagination data={messages} />
            {writable && (
                <form
                    className="market-panel market-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/messages/${conversation.id}`, {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.setData({
                                    body: '',
                                    client_token: crypto.randomUUID(),
                                }),
                        });
                    }}
                >
                    <label className="market-field">
                        <span>{t('Message')}</span>
                        <TextArea
                            rows={4}
                            required
                            maxLength={10000}
                            value={form.data.body}
                            onChange={(value) => form.setData('body', value)}
                        />
                    </label>
                    <InputError message={form.errors.body} />
                    <p className="market-muted">
                        {t(
                            'You can correct sent text for 15 minutes. Messages cannot be deleted.',
                        )}
                    </p>
                    <div>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.body.trim()}
                        >
                            {t('Send message')}
                        </Button>
                    </div>
                </form>
            )}
        </MessageLayout>
    );
}
