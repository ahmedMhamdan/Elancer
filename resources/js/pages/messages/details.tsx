// Plan 032 part 13: who the other side is, the deal behind the conversation and its files, beside the chat
// (or in a sheet on smaller screens). Everything here is already shown on the hiring and contract pages.
import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    ArrowRight,
    Building2,
    Lock,
    MapPin,
    Paperclip,
    ShieldBan,
    Star,
} from 'lucide-react';
import { ConversationAvatar, StatusMark } from '@/components/ui/chat-template';
import { useNumberFormat } from '@/components/ui/number-ticker';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import { FileSize } from '@/pages/discovery/shared';
import type { ConversationDetails, ConversationSummary } from './shared';

export function Amount({ value }: { value: string }) {
    const format = useNumberFormat(2);
    return (
        <bdi dir="ltr" className="chat-amount">
            ${format.format(Number(value))}
        </bdi>
    );
}

/** What happens next, in the words Contracts and Final offers use, and the one action when it is the viewer's move. */
export function useNextStep(conversation: ConversationSummary) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    const c = conversation;
    const first = c.counterpart.trim().split(/\s+/u)[0] ?? c.counterpart;
    const workroom = `/contracts/${c.contract_id}`;
    const due = c.due_at ? relative(c.due_at) : null;
    if (c.status === null) {
        if (c.offer?.status === 'pending')
            return c.turn === 'yours'
                ? {
                      action: t('Review the offer'),
                      href: `/offers/${c.offer.id}`,
                      line: t(':name sent you a final offer.', { name: first }),
                  }
                : {
                      line: t('Waiting for :name to answer your final offer.', {
                          name: first,
                      }),
                  };
        if (c.turn === 'yours')
            return {
                action: t('Send final offer'),
                href: `/proposals/${c.proposal_id}/offer`,
                line: t('Agree on the work here, then send the final offer.'),
            };
        return {
            line: c.is_client
                ? t('This hiring conversation is read-only.')
                : t('The client sends a final offer when ready to hire.'),
        };
    }
    const mine: Record<string, [string, string, string]> = {
        awaiting_payment: [
            t('Fund the contract to start the work.'),
            t('Fund contract'),
            `${workroom}?tab=payments`,
        ],
        active: [
            t('Submit the complete delivery before the first delivery date.'),
            t('Submit delivery'),
            `${workroom}?tab=deliveries`,
        ],
        submitted: [
            t('Review the delivery, then approve it or request a revision.'),
            t('Review the delivery'),
            `${workroom}?tab=deliveries`,
        ],
        revision_requested: [
            t('Read the requested changes and submit a revised delivery.'),
            t('Submit the revision'),
            `${workroom}?tab=deliveries`,
        ],
        cancellation_pending: [
            t('A cancellation request is open. Formal work is paused.'),
            t('Answer the request'),
            workroom,
        ],
    };
    if (c.turn === 'yours' && mine[c.status]) {
        const [line, action, href] = mine[c.status];
        return { line, action, href };
    }
    const theirs: Record<string, string> = {
        awaiting_payment: t('Work starts once the client funds it.'),
        active: due
            ? t('Delivery due :when', { when: due })
            : t(':name is working on it.', { name: first }),
        submitted: t('Waiting for the client’s review.'),
        revision_requested: due
            ? t('Revision due :when', { when: due })
            : t(':name is working on the revision.', { name: first }),
        cancellation_pending: t(
            'Waiting for an answer to the cancellation request.',
        ),
        completed: t('The contract is complete.'),
        cancelled: t('The contract was cancelled.'),
    };
    return { line: theirs[c.status] ?? '' };
}

export function DetailsPanel({
    conversation,
    details,
}: {
    conversation: ConversationSummary;
    details: ConversationDetails;
}) {
    const { t, ar } = useTranslation();
    const { demo } = useDemoSession();
    const next = useNextStep(conversation);
    const c = conversation;
    const Arrow = ar ? ArrowLeft : ArrowRight;
    const record = details.record;
    const rating = record.rating.average !== null && (
        <span>
            <Star className="size-3.5" aria-hidden="true" />
            <bdi dir="ltr">{record.rating.average.toFixed(1)}</bdi>
            <span className="sr-only">{t('out of 5')}</span> (
            {record.rating.count})
        </span>
    );
    return (
        <div className="chat-details">
            <section className="chat-details-who">
                <ConversationAvatar
                    name={c.counterpart}
                    src={c.avatar}
                    size={72}
                />
                <bdi className="chat-details-name">{c.counterpart}</bdi>
                {details.headline && <p dir="auto">{details.headline}</p>}
                <div className="chat-details-meta">
                    {details.country && (
                        <span>
                            <MapPin className="size-3.5" aria-hidden="true" />
                            <bdi>{details.country}</bdi>
                        </span>
                    )}
                    {details.company && (
                        <span>
                            <Building2
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            <bdi>{details.company}</bdi>
                        </span>
                    )}
                    {rating}
                    <span>
                        {c.is_client
                            ? record.completed === 1
                                ? t('1 completed contract')
                                : t(':count completed contracts', {
                                      count: record.completed,
                                  })
                            : record.completed === 1
                              ? t('1 hire completed on Elancer')
                              : t(':count hires completed on Elancer', {
                                    count: record.completed,
                                })}
                    </span>
                </div>
                {details.profile && (
                    <Link
                        href={`/freelancers/${details.profile}`}
                        className="chat-pill-button"
                    >
                        {t('View profile')}
                    </Link>
                )}
            </section>
            <section>
                <h3>{c.status === null ? t('Proposal') : t('Contract')}</h3>
                <dl className="chat-details-list">
                    <div>
                        <dt>{t('Project')}</dt>
                        <dd>
                            <Link href={`/jobs/${c.project.id}`} dir="auto">
                                {c.project.title}
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt>{t('Status')}</dt>
                        <dd>
                            <StatusMark status={c.status} />
                        </dd>
                    </div>
                    {c.amount && (
                        <div>
                            <dt>
                                {c.status === null
                                    ? t('Proposed')
                                    : t('Agreed')}
                            </dt>
                            <dd>
                                <Amount value={c.amount} />
                            </dd>
                        </div>
                    )}
                </dl>
                {!!next.line && (
                    <div className="chat-details-next">
                        <p>
                            {next.action && (
                                <>
                                    <span
                                        className="chat-status"
                                        data-state="open"
                                    >
                                        {t('Your move')}
                                    </span>
                                    <span aria-hidden="true">·</span>
                                </>
                            )}
                            <span>{next.line}</span>
                        </p>
                        {next.action && next.href && (
                            <Link
                                href={next.href}
                                className="chat-pill-button chat-pill-button-primary"
                            >
                                {next.action}
                            </Link>
                        )}
                    </div>
                )}
                <div className="chat-details-links">
                    {c.contract_id && (
                        <Link href={`/contracts/${c.contract_id}`}>
                            {t('Open contract')}
                            <Arrow className="size-3.5" aria-hidden="true" />
                        </Link>
                    )}
                    <Link href={`/proposals/${c.proposal_id}`}>
                        {t('View proposal')}
                        <Arrow className="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
            </section>
            <section>
                <h3>{t('Files in this conversation')}</h3>
                {demo ? (
                    <p className="chat-details-off">
                        <Lock className="size-3.5" aria-hidden="true" />
                        {t('Files are switched off in the shared demo.')}
                    </p>
                ) : details.files.length ? (
                    <>
                        <ul className="chat-details-files">
                            {details.files.map((file) => (
                                <li key={file.id}>
                                    <a
                                        href={`/messages/files/${file.id}`}
                                        download
                                    >
                                        <Paperclip
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <bdi>{file.name}</bdi>
                                        <small>
                                            <FileSize bytes={file.size} />
                                        </small>
                                    </a>
                                </li>
                            ))}
                        </ul>
                        {details.files_total > details.files.length && (
                            <p className="chat-details-off">
                                {t(
                                    'The newest :shown of :count files. Older ones are in the messages above.',
                                    {
                                        shown: details.files.length,
                                        count: details.files_total,
                                    },
                                )}
                            </p>
                        )}
                    </>
                ) : (
                    <p className="chat-details-off">{t('No files yet.')}</p>
                )}
            </section>
            <section className="chat-details-actions">
                <button
                    type="button"
                    onClick={() =>
                        router.patch(
                            `/messages/${c.id}/state`,
                            { archived: !c.archived },
                            { preserveScroll: true },
                        )
                    }
                >
                    {c.archived ? (
                        <ArchiveRestore className="size-4" aria-hidden="true" />
                    ) : (
                        <Archive className="size-4" aria-hidden="true" />
                    )}
                    {c.archived ? t('Unarchive') : t('Archive conversation')}
                </button>
                {/* Plan 032 part 10: a shared demo account blocks nobody. */}
                {!demo && (
                    <button
                        type="button"
                        className="chat-danger"
                        onClick={() => blockAccount(c.id, t)}
                    >
                        <ShieldBan className="size-4" aria-hidden="true" />
                        {t('Block account')}
                    </button>
                )}
            </section>
        </div>
    );
}

export function blockAccount(
    conversation: number,
    t: (text: string) => string,
) {
    if (
        window.confirm(
            t(
                'Block this account? New invitations and proposal contact will be stopped.',
            ),
        )
    )
        router.post(`/messages/${conversation}/block`);
}
