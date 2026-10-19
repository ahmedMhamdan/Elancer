// Final offers as an inbox (plan 032 part 7, Ahmed picked "B"): the offers on one side and the chosen one on the other,
// like Messages. /offers and /offers/{id} render this page; a row opens its offer with a partial visit. Tiles and lists
// reuse My projects' pieces; the time left is Magic UI's Animated Circular Progress Bar. The tiles and row prices count
// once when they come into view (Magic UI Number Ticker); the chosen offer's figures do not (part 8, Ahmed: counting
// on every chosen offer "becomes repitive ... and anoying").
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { motion, useReducedMotion } from 'motion/react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import TextArea from '@/components/tailadmin/textarea';
import InputError from '@/components/input-error';
import { AnimatedCircularProgressBar } from '@/components/ui/animated-circular-progress-bar';
import { BlurFade } from '@/components/ui/blur-fade';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { NumberTicker, useNumberFormat } from '@/components/ui/number-ticker';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import {
    AgreementLayout,
    contractStatusLabel,
    offerState,
    type Counterpart,
    type Offer,
    type OfferRow,
    type OfferState,
} from './shared';
import '../../../css/elancer-jobs.css';
import '../../../css/elancer-projects.css';
import '../../../css/elancer-offers.css';

type List = 'all' | 'yours' | 'theirs' | 'accepted' | 'closed';
type Step = {
    id: number;
    status: string;
    version: number;
    reason: string | null;
    created_at: string;
    closed_at: string | null;
    amount: string;
    duration_days: number;
};
type Answer = 'accept' | 'decline' | 'withdraw' | 'changes_requested';
const HOUR = 3_600_000;
// Two panes from this width of the page (the CSS container query matches it). Narrower, a row opens its offer alone,
// so the page returns to the top.
const wide = () =>
    (document.querySelector('.offers-page')?.clientWidth ?? 0) >= 800;

function Face({ person, size }: { person: Counterpart; size: number }) {
    return (
        <span
            className="offers-face"
            style={{ width: size, height: size }}
            aria-hidden="true"
        >
            {person.avatar ? (
                <img src={person.avatar} alt="" loading="lazy" />
            ) : (
                (person.name?.trim().charAt(0) ?? '?')
            )}
        </span>
    );
}

// "To Lina Haddad · Freelancer" for the client, "From Nadia Farah · Olive Studio" for the freelancer.
function Who({
    offer,
}: {
    offer: Pick<OfferRow, 'is_client' | 'counterpart'>;
}) {
    const { t } = useTranslation();
    const name = offer.counterpart.name ?? '—';
    const role = offer.is_client
        ? t('Freelancer')
        : (offer.counterpart.company ?? t('Client'));
    return (
        <>
            {offer.is_client ? t('To') : t('From')}{' '}
            <strong>
                <bdi>{name}</bdi>
            </strong>
            {' · '}
            <bdi>{role}</bdi>
        </>
    );
}

function Price({ amount, still = false }: { amount: string; still?: boolean }) {
    const format = useNumberFormat(2);
    if (still)
        return (
            <bdi dir="ltr" className="offers-price">
                ${format.format(Number(amount))}
            </bdi>
        );
    return (
        <bdi dir="ltr" className="offers-price">
            <span className="sr-only">${amount}</span>
            <span aria-hidden="true">
                $
                <NumberTicker value={Number(amount)} decimalPlaces={2} />
            </span>
        </bdi>
    );
}

function Row({
    row,
    selected,
    current,
    query,
}: {
    row: OfferRow;
    selected: boolean;
    current: boolean;
    query: string;
}) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    const state = offerState(row, t);
    const time =
        row.status === 'pending'
            ? t('Expires :when', { when: relative(row.expires_at) })
            : relative(
                  row.status === 'expired'
                      ? row.expires_at
                      : (row.closed_at ?? row.created_at),
              );
    return (
        <Link
            href={`/offers/${row.id}${query}`}
            only={['offer', 'history', 'preview']}
            preserveState
            preserveScroll={wide}
            className="offers-row"
            data-selected={selected}
            aria-current={current ? 'page' : undefined}
        >
            <Face person={row.counterpart} size={32} />
            <span className="offers-row-main">
                <span className="offers-row-line">
                    <span className="offers-row-title" dir="auto">
                        {row.project.title}
                    </span>
                    <Price amount={row.amount} />
                </span>
                <span className="offers-row-who">
                    <Who offer={row} />
                </span>
                <span className="offers-row-line">
                    <span
                        className="my-project-status"
                        data-state={state.state}
                    >
                        {state.label}
                    </span>
                    <span className="offers-row-time">{time}</span>
                </span>
            </span>
        </Link>
    );
}

// Everything that belongs to one offer and resets when another is chosen: the time left or the request, the actions,
// the answer, the terms and the history.
function OfferBody({
    offer,
    history,
    readOnly,
}: {
    offer: Offer;
    history: Step[];
    readOnly: boolean;
}) {
    const { t, ar, locale } = useTranslation();
    const relative = useRelativeTime();
    const format = useNumberFormat();
    const [confirm, setConfirm] = useState<Answer | null>(null);
    const [asking, setAsking] = useState(false);
    const [full, setFull] = useState(false);
    // "offer" carries the server's refusal ("no longer available"), as on the former offer page.
    const form = useForm({
        action: '',
        version: offer.version,
        reason: '',
        offer: '',
    });
    const pending = offer.status === 'pending';
    const answering = pending && !offer.is_client && !readOnly;
    const left = Math.max(
        0,
        Math.ceil((new Date(offer.expires_at).getTime() - Date.now()) / HOUR),
    );
    const ends = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(offer.expires_at));
    const reasons: Record<string, string> = {
        contact_blocked: t('Contact was blocked.'),
        account_restricted: t('An account was restricted.'),
        hiring_unavailable: t('Hiring is no longer available for this offer.'),
    };
    const send = (action: Answer) => {
        form.transform((data) => ({
            ...data,
            action,
            version: offer.version,
            reason: action === 'changes_requested' ? data.reason : null,
        }));
        form.patch(`/offers/${offer.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setConfirm(null),
            onError: () => setConfirm(null),
        });
    };
    const message = offer.conversation_id
        ? {
              label: t('Send a message'),
              href: `/messages/${offer.conversation_id}`,
          }
        : null;
    const proposal = {
        label: offer.is_client
            ? t('View their proposal')
            : t('View your proposal'),
        href: `/proposals/${offer.proposal_id}`,
    };
    // One main action, only when it is the viewer's move; the rest under ⋯ (as on My projects).
    // A suspended member keeps the workroom (Q64); can_replace is already false for them.
    const main =
        offer.status === 'accepted' && offer.contract
            ? {
                  label: t('Open the workroom'),
                  href: `/contracts/${offer.contract.id}`,
              }
            : offer.can_replace
              ? {
                    label: t('Send a new offer'),
                    href: `/proposals/${offer.proposal_id}/offer`,
                }
              : null;
    const menu: {
        label: string;
        href?: string;
        onSelect?: () => void;
        danger?: boolean;
    }[] = [
        proposal,
        ...(offer.is_client && offer.can_replace
            ? [
                  {
                      label: t('Review applicants'),
                      href: `/my-projects/${offer.project.id}/proposals`,
                  },
              ]
            : []),
        ...(offer.is_client && pending && !readOnly
            ? [
                  {
                      label: t('Withdraw offer…'),
                      onSelect: () => setConfirm('withdraw'),
                      danger: true,
                  },
              ]
            : []),
    ];
    const deliverables = offer.terms.deliverables;
    const long = offer.terms.scope.length > 320 || deliverables.length > 3;
    const questions: Record<Answer, [string, string, string]> = {
        accept: [
            t('Accept these exact terms and create the contract?'),
            t(
                'Acceptance closes hiring and creates a contract awaiting sandbox funding.',
            ),
            t('Accept offer'),
        ],
        decline: [
            t(
                'Decline this offer? It will no longer be available for acceptance.',
            ),
            t('The client may send a new offer while hiring is open.'),
            t('Decline offer'),
        ],
        withdraw: [
            t(
                'Withdraw this offer? It will no longer be available for acceptance.',
            ),
            t('You can send a new offer afterwards while hiring is open.'),
            t('Withdraw offer'),
        ],
        changes_requested: [
            t('Close this offer and send your requested changes?'),
            t(
                'Requesting changes closes this offer. The client may send a replacement with a new 72-hour window.',
            ),
            t('Request changes'),
        ],
    };
    return (
        <>
            {pending && (
                <div className="offers-window">
                    <AnimatedCircularProgressBar
                        className="offers-ring"
                        value={left}
                        max={72}
                        gaugePrimaryColor="var(--el-accent)"
                        gaugeSecondaryColor="var(--el-soft)"
                    >
                        <span
                            className="offers-ring-value"
                            role="img"
                            aria-label={t(':count of 72 hours left', {
                                count: left,
                            })}
                        >
                            {format.format(left)}
                            <small>{t('hours')}</small>
                        </span>
                    </AnimatedCircularProgressBar>
                    <div>
                        <strong>
                            {t('Expires :when', {
                                when: relative(offer.expires_at),
                            })}
                        </strong>
                        <span>{t('Ends :date', { date: ends })}</span>
                    </div>
                </div>
            )}
            {offer.status === 'changes_requested' && offer.reason && (
                <div className="offers-request">
                    <p>
                        {offer.is_client
                            ? t('Requested changes')
                            : t('Your requested changes')}
                    </p>
                    <blockquote dir="auto">{offer.reason}</blockquote>
                </div>
            )}
            {offer.status === 'accepted' && offer.contract && (
                <p className="offers-note">
                    {t('Contract')}:{' '}
                    <strong>
                        {contractStatusLabel(offer.contract.status, t)}
                    </strong>
                </p>
            )}
            {offer.status === 'restricted' && (
                <p className="offers-note">
                    {(offer.reason && reasons[offer.reason]) ??
                        t('Hiring is no longer available for this offer.')}
                </p>
            )}
            <div className="offers-actions">
                {message && (
                    <Link className="my-project-link" href={message.href}>
                        {message.label}
                    </Link>
                )}
                {main && (
                    <InteractiveHoverButton
                        href={main.href}
                        className="offers-main"
                    >
                        {main.label}
                    </InteractiveHoverButton>
                )}
                <DropdownMenu dir={ar ? 'rtl' : 'ltr'} modal={false}>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="my-project-more"
                            aria-label={t('Offer actions')}
                        >
                            <MoreHorizontal
                                className="size-5"
                                aria-hidden="true"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {menu.map((item) =>
                            item.href ? (
                                <DropdownMenuItem key={item.label} asChild>
                                    <Link href={item.href}>{item.label}</Link>
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    key={item.label}
                                    onSelect={item.onSelect}
                                    className={
                                        item.danger
                                            ? 'text-destructive'
                                            : undefined
                                    }
                                >
                                    {item.label}
                                </DropdownMenuItem>
                            ),
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <InputError message={form.errors.offer} />
            {answering && (
                <section
                    className="offers-answer"
                    aria-labelledby="offer-answer"
                >
                    <h3 id="offer-answer">{t('Your answer')}</h3>
                    <p>
                        {t(
                            'Acceptance closes hiring and creates a contract awaiting sandbox funding.',
                        )}
                    </p>
                    <div className="market-actions">
                        <Button
                            disabled={form.processing}
                            onClick={() => setConfirm('accept')}
                        >
                            {t('Accept offer')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setAsking((open) => !open)}
                            aria-expanded={asking}
                            aria-controls="offer-changes-form"
                        >
                            {t('Request changes')}
                        </Button>
                        <Button
                            variant="danger-outline"
                            disabled={form.processing}
                            onClick={() => setConfirm('decline')}
                        >
                            {t('Decline offer')}
                        </Button>
                    </div>
                    {asking && (
                        <div
                            id="offer-changes-form"
                            className="offers-changes market-field"
                        >
                            <label htmlFor="offer-changes">
                                {t('Requested changes')}
                            </label>
                            <TextArea
                                id="offer-changes"
                                rows={4}
                                value={form.data.reason}
                                onChange={(value) =>
                                    form.setData('reason', value)
                                }
                                maxLength={3000}
                            />
                            <InputError message={form.errors.reason} />
                            <p className="market-muted">
                                {t(
                                    'Requesting changes closes this offer. The client may send a replacement with a new 72-hour window.',
                                )}
                            </p>
                            <div>
                                <Button
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={() =>
                                        setConfirm('changes_requested')
                                    }
                                >
                                    {t('Send requested changes')}
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            )}
            <section className="offers-section" aria-labelledby="offer-terms">
                <h3 id="offer-terms">{t('Terms')}</h3>
                <p
                    dir="auto"
                    className="offers-scope"
                    data-clamped={!full && long}
                >
                    {offer.terms.scope}
                </p>
                <ol className="offers-deliverables">
                    {(full ? deliverables : deliverables.slice(0, 3)).map(
                        (item, index) => (
                            <li key={index} dir="auto">
                                {item}
                            </li>
                        ),
                    )}
                </ol>
                {long && (
                    <button
                        type="button"
                        className="offers-more"
                        aria-expanded={full}
                        onClick={() => setFull((open) => !open)}
                    >
                        {full ? t('Show less') : t('Show full terms')}
                    </button>
                )}
                {pending && (
                    <p className="offers-footnote">
                        {t(
                            'Delivery time starts only after verified sandbox funding. Weekends count as calendar days.',
                        )}
                    </p>
                )}
            </section>
            {history.length > 0 && (
                <section
                    className="offers-section"
                    aria-labelledby="offer-history"
                >
                    <h3 id="offer-history">{t('Offer history')}</h3>
                    <ol className="offers-timeline">
                        {[...history].reverse().map((step, index) => {
                            const state = stepState(step.status);
                            const label = t('Offer :number', {
                                number: index + 1,
                            });
                            return (
                                <li
                                    key={step.id}
                                    data-state={state}
                                    aria-current={
                                        step.id === offer.id
                                            ? 'step'
                                            : undefined
                                    }
                                >
                                    <div className="offers-step">
                                        {step.id === offer.id ? (
                                            <span>{label}</span>
                                        ) : (
                                            <Link
                                                href={`/offers/${step.id}`}
                                                only={[
                                                    'offer',
                                                    'history',
                                                    'preview',
                                                ]}
                                                preserveState
                                                preserveScroll
                                            >
                                                {label}
                                            </Link>
                                        )}
                                        <time dateTime={step.created_at}>
                                            {relative(step.created_at)}
                                        </time>
                                    </div>
                                    <p className="offers-step-sub">
                                        {stepLabel(step.status, t)} ·{' '}
                                        <bdi dir="ltr">${step.amount}</bdi> ·{' '}
                                        {t('Delivery days: :count', {
                                            count: step.duration_days,
                                        })}
                                    </p>
                                </li>
                            );
                        })}
                    </ol>
                </section>
            )}
            <Dialog
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (!open) setConfirm(null);
                }}
            >
                <DialogContent>
                    {confirm && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {questions[confirm][0]}
                                </DialogTitle>
                                <DialogDescription>
                                    {questions[confirm][1]}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex justify-end gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() => setConfirm(null)}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button
                                    variant={
                                        confirm === 'accept'
                                            ? 'primary'
                                            : confirm === 'changes_requested'
                                              ? 'outline'
                                              : 'danger'
                                    }
                                    disabled={form.processing}
                                    onClick={() => send(confirm)}
                                >
                                    {questions[confirm][2]}
                                </Button>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function stepState(status: string): OfferState {
    return status === 'pending'
        ? 'open'
        : status === 'accepted'
          ? 'hired'
          : status === 'changes_requested'
            ? 'closed'
            : status === 'restricted'
              ? 'hidden'
              : 'draft';
}
function stepLabel(status: string, t: (text: string) => string): string {
    const labels: Record<string, string> = {
        pending: t('Waiting for an answer'),
        changes_requested: t('Changes requested'),
        accepted: t('Offer accepted'),
        declined: t('Offer declined'),
        withdrawn: t('Offer withdrawn'),
        expired: t('Expired'),
        restricted: t('Closed by restriction'),
    };
    return labels[status] ?? status;
}

function OfferDetail({
    offer,
    history,
    readOnly,
    back,
}: {
    offer: Offer;
    history: Step[];
    readOnly: boolean;
    back: string;
}) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    const format = useNumberFormat();
    const state = offerState(offer, t);
    const when =
        offer.status === 'pending'
            ? offer.is_client
                ? t('Sent :when', { when: relative(offer.created_at) })
                : t('Received :when', { when: relative(offer.created_at) })
            : relative(
                  offer.status === 'expired'
                      ? offer.expires_at
                      : (offer.closed_at ?? offer.created_at),
              );
    return (
        <section className="offers-detail" aria-labelledby="offer-title">
            <Link href={back} className="offers-back">
                <ArrowLeft
                    className="size-4 rtl:-scale-x-100"
                    aria-hidden="true"
                />
                {t('All offers')}
            </Link>
            <div className="my-project-top">
                <span className="my-project-status" data-state={state.state}>
                    {state.label}
                </span>
                <time dateTime={offer.created_at}>{when}</time>
            </div>
            <h2 id="offer-title" dir="auto">
                <Link href={`/jobs/${offer.project.id}`}>
                    {offer.project.title}
                </Link>
            </h2>
            <p className="offers-who">
                <Face person={offer.counterpart} size={28} />
                <span>
                    <Who offer={offer} />
                </span>
            </p>
            <dl className="offers-figures">
                <div>
                    <dt>{t('Price')}</dt>
                    <dd>
                        <Price amount={offer.terms.amount} still />
                    </dd>
                </div>
                <div>
                    <dt>{t('Delivery days')}</dt>
                    <dd>{format.format(offer.terms.duration_days)}</dd>
                </div>
                <div>
                    <dt>{t('Revision rounds')}</dt>
                    <dd>{format.format(offer.terms.revision_rounds)}</dd>
                </div>
            </dl>
            <OfferBody
                key={offer.id}
                offer={offer}
                history={history}
                readOnly={readOnly}
            />
        </section>
    );
}

export default function Offers({
    offers,
    list,
    counts,
    offer,
    history,
    preview,
    readOnly,
}: {
    offers: Page<OfferRow>;
    list: List;
    counts: Record<List, number>;
    offer: Offer | null;
    history: Step[];
    // On /offers the first offer is shown beside the list on wide screens; /offers/{id} is a chosen offer.
    preview: boolean;
    // A suspended member reads the offers; nothing on them can be changed.
    readOnly: boolean;
}) {
    const { t } = useTranslation();
    const reduced = useReducedMotion();
    const { auth } = usePage().props;
    const href = (name: List) =>
        name === 'all' ? '/offers' : `/offers?list=${name}`;
    const query = list === 'all' ? '' : `?list=${list}`;
    const tiles: [List, string][] = [
        ['yours', t('Waiting for your reply')],
        ['theirs', t('Waiting for their reply')],
        ['accepted', t('Accepted offers')],
        ['closed', t('Closed offers')],
    ];
    const lists: [List, string][] = [['all', t('All')], ...tiles];
    const focus = offer && !preview ? 'detail' : 'list';
    const client = auth.user?.workspace_role !== 'freelancer';
    return (
        <AgreementLayout
            title={t('Final offers')}
            lead={t(
                'Final offers you sent and received. Each one stays open for 72 hours.',
            )}
        >
            <div className="offers-page" data-focus={focus}>
                <div className="my-projects-tiles">
                    {tiles.map(([name, label], index) => (
                        <BlurFade key={name} delay={index * 0.05}>
                            <Link
                                href={href(name)}
                                className="my-projects-tile"
                                aria-current={
                                    list === name ? 'page' : undefined
                                }
                            >
                                <strong>
                                    <NumberTicker value={counts[name]} />
                                </strong>
                                <span>{label}</span>
                            </Link>
                        </BlurFade>
                    ))}
                </div>
                <nav
                    className="my-projects-lists"
                    aria-label={t('Offer lists')}
                >
                    {lists.map(([name, label]) => (
                        <Link
                            key={name}
                            href={href(name)}
                            aria-current={list === name ? 'page' : undefined}
                            preserveScroll
                        >
                            {list === name && (
                                <motion.span
                                    layoutId="offers-list"
                                    className="my-projects-highlight"
                                    transition={
                                        reduced
                                            ? { duration: 0 }
                                            : {
                                                  type: 'spring',
                                                  stiffness: 350,
                                                  damping: 32,
                                              }
                                    }
                                />
                            )}
                            <span>{label}</span>
                            <span>
                                <small>{counts[name]}</small>
                            </span>
                        </Link>
                    ))}
                </nav>
                {offers.data.length === 0 && !offer ? (
                    <div className="jobs-empty">
                        <h2>
                            {list === 'all'
                                ? t('No final offers yet.')
                                : t('Nothing in this list yet')}
                        </h2>
                        <p>
                            {list === 'all'
                                ? t(
                                      'Clients can send a final offer from a submitted proposal.',
                                  )
                                : t(
                                      'Offers move here as their status changes.',
                                  )}
                        </p>
                        {list === 'all' && (
                            <Link
                                href={client ? '/my-projects' : '/jobs'}
                                className="my-project-link offers-empty-link"
                            >
                                {client
                                    ? t('Go to My projects')
                                    : t('Find work')}
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="offers-inbox">
                        <section
                            className="offers-list"
                            aria-label={t('Your final offers')}
                        >
                            <ul>
                                {offers.data.map((row, index) => (
                                    <li key={row.id}>
                                        <BlurFade
                                            delay={
                                                0.1 + Math.min(index, 6) * 0.04
                                            }
                                        >
                                            <Row
                                                row={row}
                                                selected={row.id === offer?.id}
                                                current={
                                                    row.id === offer?.id &&
                                                    !preview
                                                }
                                                query={query}
                                            />
                                        </BlurFade>
                                    </li>
                                ))}
                            </ul>
                            {offers.data.length === 0 && (
                                <p className="offers-list-empty">
                                    {t('Nothing in this list yet')}
                                </p>
                            )}
                            {offers.last_page > 1 && (
                                <div className="offers-pages">
                                    <Pagination data={offers} />
                                </div>
                            )}
                        </section>
                        {offer && (
                            <BlurFade
                                delay={0.15}
                                className="offers-detail-wrap"
                            >
                                <OfferDetail
                                    offer={offer}
                                    history={history}
                                    readOnly={readOnly}
                                    back={href(list)}
                                />
                            </BlurFade>
                        )}
                    </div>
                )}
            </div>
        </AgreementLayout>
    );
}
