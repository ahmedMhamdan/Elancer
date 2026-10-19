// Contracts (plan 032 part 8, Ahmed picked "B"): one card per contract with where it stands as four steps (Funding,
// Delivery, Review, Completed) and, when it is the viewer's move, the workroom's own sentence and one main action.
// Finished and cancelled contracts are short cards. Tiles, lists, status square, quiet link and ⋯ come from My
// projects; the steps follow Origin UI's Stepper (https://coss.com/origin/stepper) with the site's square, as
// TailAdmin has no stepper. The tiles and card prices count once, on the first visit (Magic UI Number Ticker); a list
// switch keeps the page and shows the new cards at once.
import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
    type ReactNode,
} from 'react';
import { motion, useReducedMotion } from 'motion/react';
import Pagination from '@/components/tailadmin/pagination';
import { BlurFade } from '@/components/ui/blur-fade';
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
import { AgreementLayout, type Counterpart } from '@/pages/offers/shared';
import '../../../css/elancer-jobs.css';
import '../../../css/elancer-projects.css';
import '../../../css/elancer-contracts.css';

type List = 'all' | 'yours' | 'theirs' | 'completed' | 'cancelled';
type Row = {
    id: number;
    project_id: number;
    conversation_id: number;
    status: string;
    created_at: string;
    funded_at: string | null;
    delivery_due_at: string | null;
    revision_due_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    revisions_used: number;
    title: string;
    amount: string;
    duration_days: number;
    revision_rounds: number;
    is_client: boolean;
    counterpart: Counterpart;
    deliveries: number;
    last_delivery_at: string | null;
    revision_asked_at: string | null;
    cancellation: {
        status: string;
        prior_status: string;
        created_at: string;
    } | null;
    overdue: boolean;
    turn: 'yours' | 'theirs' | null;
};
const DAY = 86_400_000;
// The status as the site's small square; hollow while the contract waits for funding.
const STATE: Record<string, string> = {
    awaiting_payment: 'wait',
    active: 'open',
    submitted: 'hired',
    revision_requested: 'closed',
    cancellation_pending: 'hidden',
    completed: 'draft',
    cancelled: 'hidden',
};
const share = (from: string, to: string) => {
    const start = new Date(from).getTime();
    const end = new Date(to).getTime();
    return end > start
        ? Math.min(1, Math.max(0, (Date.now() - start) / (end - start)))
        : 1;
};

function useShortDate() {
    const { locale } = useTranslation();
    const format = useMemo(
        () =>
            new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric' }),
        [locale],
    );
    return (value: string) => format.format(new Date(value));
}

function Face({ person }: { person: Counterpart }) {
    return (
        <span className="contracts-face" aria-hidden="true">
            {person.avatar ? (
                <img src={person.avatar} alt="" loading="lazy" />
            ) : (
                (person.name?.trim().charAt(0) ?? '?')
            )}
        </span>
    );
}

// "With Lina Haddad · Freelancer" for the client, "With Nadia Farah · Olive Studio" for the freelancer.
function Who({ contract }: { contract: Row }) {
    const { t } = useTranslation();
    const role = contract.is_client
        ? t('Freelancer')
        : (contract.counterpart.company ?? t('Client'));
    return (
        <span className="my-project-people">
            <Face person={contract.counterpart} />
            <span>
                {t('With')}{' '}
                <strong>
                    <bdi>{contract.counterpart.name ?? '—'}</bdi>
                </strong>
                {' · '}
                <bdi>{role}</bdi>
            </span>
        </span>
    );
}

function Price({ amount, count }: { amount: string; count: boolean }) {
    const format = useNumberFormat(2);
    return (
        <bdi dir="ltr" className="contracts-price">
            {count ? (
                <>
                    <span className="sr-only">${amount}</span>
                    <span aria-hidden="true">
                        $
                        <NumberTicker
                            value={Number(amount)}
                            decimalPlaces={2}
                        />
                    </span>
                </>
            ) : (
                `$${format.format(Number(amount))}`
            )}
        </bdi>
    );
}

function plural(
    t: (text: string, values?: Record<string, string | number>) => string,
    count: number,
    one: string,
    many: string,
) {
    return count === 1 ? t(one) : t(many, { count });
}

// Where the contract stands. The line after the current step fills with the days used toward its date.
function Steps({ contract, still }: { contract: Row; still: boolean }) {
    const { t } = useTranslation();
    const date = useShortDate();
    const c = contract;
    const deliveries = plural(
        t,
        c.deliveries,
        '1 delivery',
        ':count deliveries',
    );
    const subs: ReactNode[] = ['', '', '', ''];
    let at = 0;
    let fill = 0;
    let stop = false;
    if (c.funded_at) subs[0] = date(c.funded_at);
    switch (c.status) {
        case 'awaiting_payment':
            subs[0] = t('Since :date', { date: date(c.created_at) });
            break;
        case 'active':
            at = 1;
            if (c.funded_at && c.delivery_due_at) {
                fill = share(c.funded_at, c.delivery_due_at);
                const start = new Date(c.funded_at).getTime();
                const total = Math.max(
                    1,
                    Math.round(
                        (new Date(c.delivery_due_at).getTime() - start) / DAY,
                    ),
                );
                const late = Math.floor(
                    (Date.now() - new Date(c.delivery_due_at).getTime()) / DAY,
                );
                subs[1] = c.overdue ? (
                    <span className="contracts-late">
                        {plural(
                            t,
                            Math.max(1, late),
                            '1 day late',
                            ':count days late',
                        )}
                    </span>
                ) : (
                    t('Day :day of :total', {
                        day: Math.min(
                            total,
                            Math.max(1, Math.ceil((Date.now() - start) / DAY)),
                        ),
                        total,
                    })
                );
            }
            break;
        case 'revision_requested':
            at = 1;
            subs[1] = t('Revision :used of :rounds', {
                used: c.revisions_used,
                rounds: c.revision_rounds,
            });
            if (c.revision_asked_at && c.revision_due_at)
                fill = share(c.revision_asked_at, c.revision_due_at);
            break;
        case 'submitted':
            at = 2;
            subs[1] = deliveries;
            subs[2] = c.is_client ? t('Your turn') : t('With the client');
            break;
        case 'cancellation_pending':
            at = c.cancellation?.prior_status === 'submitted' ? 2 : 1;
            stop = true;
            if (at === 2) subs[1] = deliveries;
            subs[at] = t('Cancellation asked');
            break;
        case 'completed':
            at = 4;
            subs[1] = deliveries;
            subs[2] =
                c.revisions_used === 0
                    ? t('No revisions')
                    : t(':used of :rounds revisions', {
                          used: c.revisions_used,
                          rounds: c.revision_rounds,
                      });
            if (c.completed_at) subs[3] = date(c.completed_at);
            break;
    }
    const names = [t('Funding'), t('Delivery'), t('Review'), t('Completed')];
    return (
        <ol
            className="contracts-steps"
            data-state={STATE[c.status]}
            data-still={still || undefined}
            aria-label={t('Where the contract stands')}
        >
            {names.map((name, index) => {
                const state =
                    index < at
                        ? 'done'
                        : index === at
                          ? stop
                              ? 'stop'
                              : 'now'
                          : 'next';
                return (
                    <li
                        key={name}
                        data-step={state}
                        aria-current={index === at ? 'step' : undefined}
                    >
                        <i aria-hidden="true" />
                        {state === 'now' && fill > 0 && index < 3 && (
                            <span
                                className="contracts-steps-fill"
                                style={{ '--fill': fill } as CSSProperties}
                                aria-hidden="true"
                            />
                        )}
                        <b>{name}</b>
                        <small>{subs[index] || ' '}</small>
                    </li>
                );
            })}
        </ol>
    );
}

function ContractCard({
    contract,
    enter,
    index,
}: {
    contract: Row;
    enter: boolean;
    index: number;
}) {
    const { t, ar, locale } = useTranslation();
    const relative = useRelativeTime();
    const date = useShortDate();
    // Decided once, when the card first appears: only the first visit fades in and counts.
    const [animate] = useState(enter);
    const c = contract;
    const workroom = `/contracts/${c.id}`;
    const labels: Record<string, string> = {
        awaiting_payment: t('Awaiting funding'),
        active: t('In progress'),
        submitted: t('Delivered, awaiting review'),
        revision_requested: t('Revision requested'),
        cancellation_pending: t('Cancellation pending'),
        completed: t('Completed'),
        cancelled: t('Cancelled'),
    };
    const late = c.delivery_due_at
        ? Math.max(
              1,
              Math.floor(
                  (Date.now() - new Date(c.delivery_due_at).getTime()) / DAY,
              ),
          )
        : 1;
    // One time per card, in words; the full date is in its title.
    const [when, at]: [string, string | null] = (() => {
        switch (c.status) {
            case 'awaiting_payment':
                return [
                    t('Accepted :when', { when: relative(c.created_at) }),
                    c.created_at,
                ];
            case 'active':
                return c.overdue
                    ? [
                          plural(t, late, '1 day late', ':count days late'),
                          c.delivery_due_at,
                      ]
                    : c.delivery_due_at
                      ? [
                            t('Delivery due :when', {
                                when: relative(c.delivery_due_at),
                            }),
                            c.delivery_due_at,
                        ]
                      : ['', null];
            case 'submitted':
                return c.last_delivery_at
                    ? [
                          t('Delivered :when', {
                              when: relative(c.last_delivery_at),
                          }),
                          c.last_delivery_at,
                      ]
                    : ['', null];
            case 'revision_requested':
                return c.revision_due_at
                    ? [
                          t('Revision due :when', {
                              when: relative(c.revision_due_at),
                          }),
                          c.revision_due_at,
                      ]
                    : c.revision_asked_at
                      ? [
                            t('Revision asked :when', {
                                when: relative(c.revision_asked_at),
                            }),
                            c.revision_asked_at,
                        ]
                      : ['', null];
            case 'cancellation_pending':
                return c.cancellation
                    ? [
                          t('Cancellation asked :when', {
                              when: relative(c.cancellation.created_at),
                          }),
                          c.cancellation.created_at,
                      ]
                    : ['', null];
            case 'completed':
                return c.completed_at
                    ? [
                          t('Completed :when', {
                              when: relative(c.completed_at),
                          }),
                          c.completed_at,
                      ]
                    : ['', null];
            default:
                return c.cancelled_at
                    ? [
                          t('Cancelled :when', {
                              when: relative(c.cancelled_at),
                          }),
                          c.cancelled_at,
                      ]
                    : ['', null];
        }
    })();
    const finished = c.status === 'completed' || c.status === 'cancelled';
    // The workroom's own sentence and the one main action, only when it is the viewer's move.
    const next: Record<string, [string, string, string]> = {
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
    const move = c.turn === 'yours' ? next[c.status] : undefined;
    const due =
        c.status === 'active'
            ? c.delivery_due_at &&
              t('Due :date', { date: date(c.delivery_due_at) })
            : c.status === 'revision_requested' && c.revision_due_at
              ? t('Revision due :date', { date: date(c.revision_due_at) })
              : null;
    const card = (
        <article className="my-project contracts-card" data-short={finished}>
            <div className="my-project-top">
                <span
                    className="my-project-status"
                    data-state={STATE[c.status]}
                >
                    {labels[c.status] ?? c.status}
                </span>
                {at && (
                    <time
                        dateTime={at}
                        title={new Date(at).toLocaleString(locale, {
                            dateStyle: 'full',
                        })}
                        className={
                            c.status === 'active' && c.overdue
                                ? 'contracts-late'
                                : undefined
                        }
                    >
                        {when}
                    </time>
                )}
            </div>
            <div className="contracts-head">
                <h2 dir="auto">
                    <Link href={workroom}>{c.title}</Link>
                </h2>
                <Price amount={c.amount} count={animate} />
            </div>
            {!finished && (
                <>
                    <div className="my-project-meta">
                        <span>
                            {plural(t, c.duration_days, '1 day', ':count days')}
                        </span>
                        <span>
                            {c.revision_rounds === 0
                                ? t('No revision rounds')
                                : plural(
                                      t,
                                      c.revision_rounds,
                                      '1 revision round',
                                      ':count revision rounds',
                                  )}
                        </span>
                        {due && <span>{due}</span>}
                    </div>
                    <Steps contract={c} still={!animate} />
                    {move && <p className="contracts-next">{move[0]}</p>}
                </>
            )}
            <div className="my-project-foot">
                <Who contract={c} />
                <div className="my-project-actions">
                    {finished && (
                        <span className="contracts-facts">
                            {c.status === 'completed'
                                ? `${plural(t, c.deliveries, '1 delivery', ':count deliveries')} · ${
                                      c.revisions_used === 0
                                          ? t('No revisions')
                                          : t(':used of :rounds revisions', {
                                                used: c.revisions_used,
                                                rounds: c.revision_rounds,
                                            })
                                  }`
                                : c.funded_at
                                  ? t('Refunded')
                                  : t('Cancelled before funding')}
                        </span>
                    )}
                    {move && (
                        <InteractiveHoverButton
                            href={move[2]}
                            className="contracts-main"
                        >
                            {move[1]}
                        </InteractiveHoverButton>
                    )}
                    <Link className="my-project-link" href={workroom}>
                        {t('Open the workroom')}
                    </Link>
                    {!finished && (
                        <DropdownMenu dir={ar ? 'rtl' : 'ltr'} modal={false}>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="my-project-more"
                                    aria-label={t('Contract actions')}
                                >
                                    <MoreHorizontal
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/messages/${c.conversation_id}`}
                                    >
                                        {t('Send a message')}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`${workroom}?tab=agreement`}>
                                        {t('View the agreement')}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`/jobs/${c.project_id}`}>
                                        {t('View project')}
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
        </article>
    );
    return animate ? (
        <BlurFade delay={0.1 + Math.min(index, 6) * 0.05}>{card}</BlurFade>
    ) : (
        card
    );
}

export default function Contracts({
    contracts,
    list,
    counts,
    agreed,
}: {
    contracts: Page<Row>;
    list: List;
    counts: Record<List, number>;
    agreed: string;
}) {
    const { t } = useTranslation();
    const reduced = useReducedMotion();
    // Cards shown after the first visit (a list switch) appear at once.
    const visited = useRef(false);
    useEffect(() => {
        visited.current = true;
    }, []);
    const href = (name: List) =>
        name === 'all' ? '/contracts' : `/contracts?list=${name}`;
    const tiles: [Exclude<List, 'all' | 'cancelled'>, string][] = [
        ['yours', t('Needs you')],
        ['theirs', t('Waiting on them')],
        ['completed', t('Completed')],
    ];
    const lists: [List, string][] = [
        ['all', t('All')],
        ...tiles,
        ['cancelled', t('Cancelled')],
    ];
    // A list switch reloads only the cards and keeps the page, so nothing counts or fades again.
    const visit = {
        preserveScroll: true,
        preserveState: true,
        only: ['contracts', 'list'],
    };
    return (
        <AgreementLayout title={t('Contracts')} href="/contracts">
            <div className="contracts-page">
                <div className="my-projects-tiles">
                    {tiles.map(([name, label], index) => (
                        <BlurFade key={name} delay={index * 0.05}>
                            <Link
                                href={href(name)}
                                {...visit}
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
                    <BlurFade delay={0.15}>
                        <div className="my-projects-tile contracts-total">
                            <strong>
                                <Price amount={agreed} count />
                            </strong>
                            <span>{t('Agreed in total')}</span>
                        </div>
                    </BlurFade>
                </div>
                <nav
                    className="my-projects-lists"
                    aria-label={t('Contract lists')}
                >
                    {lists.map(([name, label]) => (
                        <Link
                            key={name}
                            href={href(name)}
                            {...visit}
                            aria-current={list === name ? 'page' : undefined}
                        >
                            {list === name && (
                                <motion.span
                                    layoutId="contracts-list"
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
                {contracts.data.length === 0 ? (
                    <div className="jobs-empty">
                        <h2>
                            {list === 'all'
                                ? t('No contracts yet.')
                                : t('Nothing in this list yet')}
                        </h2>
                        <p>
                            {list === 'all'
                                ? t(
                                      'A contract is created when the freelancer accepts a final offer.',
                                  )
                                : t(
                                      'Contracts move here as their status changes.',
                                  )}
                        </p>
                        {list === 'all' && (
                            <Link
                                href="/offers"
                                className="my-project-link contracts-empty-link"
                            >
                                {t('Go to Final offers')}
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="my-projects-cards">
                        {contracts.data.map((contract, index) => (
                            <ContractCard
                                key={contract.id}
                                contract={contract}
                                enter={!visited.current}
                                index={index}
                            />
                        ))}
                    </div>
                )}
                {contracts.last_page > 1 && <Pagination data={contracts} />}
            </div>
        </AgreementLayout>
    );
}
