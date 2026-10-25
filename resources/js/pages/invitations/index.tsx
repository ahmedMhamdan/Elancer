// Invitations as an inbox (plan 032 part 14, Ahmed picked "B"), like Final offers: the invitations on one side and the
// chosen one on the other. /invitations and /invitations/{id} render this page; a row opens its invitation with a partial
// visit. The lists, status square, quiet link, ⋯ and application window come from My projects; the inbox frame, rows and
// the back link from Final offers. Where an invitation stands comes from the server (InvitationController).
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { motion, useReducedMotion } from 'motion/react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import InputError from '@/components/input-error';
import RatingSummary, { CompletedProjects } from '@/components/rating-summary';
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
import AppLayout from '@/layouts/app-layout';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import { Money, type Page } from '@/pages/discovery/shared';
import type {
    Counterpart,
    Invitation,
    InvitationRow,
    InvitationState,
} from './shared';
import '../../../css/elancer-jobs.css';
import '../../../css/elancer-projects.css';
import '../../../css/elancer-offers.css';
import '../../../css/elancer-invitations.css';

type List = 'all' | InvitationState;
type Box = 'sent' | 'received';
type Translate = (
    text: string,
    params?: Record<string, string | number>,
) => string;
// Two panes from this width of the page (the CSS container query matches it). Narrower, a row opens its invitation
// alone, so the page returns to the top.
const wide = () =>
    (document.querySelector('.invitations-page')?.clientWidth ?? 0) >= 800;
// The site's small square: green while it waits, blue once applied, grey declined, amber closed.
const SQUARE: Record<InvitationState, string> = {
    waiting: 'open',
    applied: 'hired',
    declined: 'draft',
    closed: 'closed',
};

function stateLabel(
    row: Pick<InvitationRow, 'state' | 'status'>,
    sent: boolean,
    t: Translate,
): string {
    switch (row.state) {
        case 'waiting':
            return sent
                ? t('Waiting for an answer')
                : t('Waiting for your answer');
        case 'applied':
            return sent ? t('Applied') : t('You applied');
        case 'declined':
            return sent ? t('Declined') : t('You declined');
        default:
            return row.status === 'cancelled'
                ? t('Cancelled')
                : t('Applications closed');
    }
}

// When it got where it stands, in words: "Sent 8 hours ago", "Declined 2 days ago".
function useStateTime() {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    return (
        row: Pick<InvitationRow, 'state' | 'status' | 'state_at' | 'sent_at'>,
        sent: boolean,
    ) => {
        const when = row.state_at ? relative(row.state_at) : null;
        switch (row.state) {
            case 'waiting':
                return t('Sent :when', { when: relative(row.sent_at) });
            case 'applied':
                return when ? t('Proposal sent :when', { when }) : null;
            case 'declined':
                return when ? t('Declined :when', { when }) : null;
            default:
                if (!when) return null;
                if (row.status === 'cancelled')
                    return t('Cancelled :when', { when });
                return sent
                    ? t('Closed :when without an answer', { when })
                    : t('Closed :when before you answered', { when });
        }
    };
}

function FullTime({ value, children }: { value: string; children: string }) {
    const { locale } = useTranslation();
    return (
        <time
            dateTime={value}
            title={new Intl.DateTimeFormat(locale, {
                dateStyle: 'full',
                timeStyle: 'short',
            }).format(new Date(value))}
        >
            {children}
        </time>
    );
}

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
                person.name.trim().charAt(0) || '?'
            )}
        </span>
    );
}

function Row({
    row,
    sent,
    selected,
    current,
    query,
}: {
    row: InvitationRow;
    sent: boolean;
    selected: boolean;
    current: boolean;
    query: string;
}) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    const time = row.state === 'waiting' ? row.sent_at : row.state_at;
    return (
        <Link
            href={`/invitations/${row.id}${query}`}
            only={['invitation', 'preview']}
            preserveState
            preserveScroll={wide}
            className="offers-row"
            data-selected={selected}
            aria-current={current ? 'page' : undefined}
        >
            <Face person={row.counterpart} size={36} />
            <span className="offers-row-main">
                <span className="offers-row-line">
                    <strong className="invitations-row-name">
                        <bdi>{row.counterpart.name}</bdi>
                    </strong>
                    {time && (
                        <span className="offers-row-time">
                            {relative(time)}
                        </span>
                    )}
                </span>
                <span className="offers-row-title" dir="auto">
                    {row.project.title}
                </span>
                <span
                    className="my-project-status"
                    data-state={SQUARE[row.state]}
                >
                    {stateLabel(row, sent, t)}
                </span>
            </span>
        </Link>
    );
}

// The application window as My projects draws it: the days used of the project's window, or when it closed.
function Window({ project }: { project: Invitation['project'] }) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    if (!('application_closes_at' in project) || !project.application_closes_at)
        return <span>{t('Closed')}</span>;
    const closes = new Date(project.application_closes_at).getTime();
    // A project can stop taking applications before its date (hired, or closed by its client): then no time is given.
    if (!project.open)
        return (
            <span>
                {closes <= Date.now()
                    ? t('Closed :when', {
                          when: relative(project.application_closes_at),
                      })
                    : t('Applications closed')}
            </span>
        );
    const opened = new Date(project.published_at).getTime();
    const share = Math.min(
        100,
        Math.max(
            4,
            ((Date.now() - opened) / Math.max(1, closes - opened)) * 100,
        ),
    );
    return (
        <span className="invitations-window">
            <FullTime value={project.application_closes_at}>
                {t('Closes :when', {
                    when: relative(project.application_closes_at),
                })}
            </FullTime>
            <span className="my-project-window-bar" aria-hidden="true">
                <span style={{ width: share + '%' }} />
            </span>
        </span>
    );
}

// Everything that belongs to one invitation and resets when another is chosen.
function InvitationDetail({
    invitation,
    sent,
    readOnly,
    back,
}: {
    invitation: Invitation;
    sent: boolean;
    readOnly: boolean;
    back: string;
}) {
    const { t, ar } = useTranslation();
    const relative = useRelativeTime();
    const stateTime = useStateTime();
    const { demo } = useDemoSession();
    const [confirm, setConfirm] = useState<'decline' | 'block' | null>(null);
    const form = useForm({ action: '', version: invitation.version });
    const project = invitation.project;
    const person = invitation.person;
    const fact = stateTime(invitation, sent);
    const respond = (action: 'decline' | 'resend') => {
        form.transform(() => ({ action, version: invitation.version }));
        form.patch(`/invitations/${invitation.id}`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setConfirm(null),
        });
    };
    const events: Record<string, string> = {
        sent: t('Invitation sent'),
        resent: t('Invitation resent'),
        declined: t('Declined'),
        accepted: t('Proposal submitted'),
        cancelled: t('Cancelled'),
    };
    const later =
        invitation.next_resend_at &&
        new Date(invitation.next_resend_at).getTime() > Date.now();
    const menu = [
        ...(sent && person.profile
            ? [
                  {
                      label: t('View profile'),
                      href: `/freelancers/${person.profile}`,
                  },
              ]
            : []),
        ...(invitation.public
            ? [{ label: t('View project'), href: `/jobs/${project.id}` }]
            : []),
    ];
    const blockable = !demo && !readOnly;
    return (
        <section className="offers-detail" aria-labelledby="invitation-title">
            <Link href={back} className="offers-back">
                <ArrowLeft
                    className="size-4 rtl:-scale-x-100"
                    aria-hidden="true"
                />
                {t('All invitations')}
            </Link>
            <div className="my-project-top">
                <span
                    className="my-project-status"
                    data-state={SQUARE[invitation.state]}
                >
                    {stateLabel(invitation, sent, t)}
                </span>
                {fact && <span>{fact}</span>}
                {(menu.length > 0 || blockable) && (
                    <DropdownMenu dir={ar ? 'rtl' : 'ltr'} modal={false}>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="my-project-more invitations-more"
                                aria-label={t('Invitation actions')}
                            >
                                <MoreHorizontal
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {menu.map((item) => (
                                <DropdownMenuItem key={item.href} asChild>
                                    <Link href={item.href}>{item.label}</Link>
                                </DropdownMenuItem>
                            ))}
                            {blockable && (
                                <DropdownMenuItem
                                    className="text-destructive"
                                    onSelect={() => setConfirm('block')}
                                >
                                    {t('Block account…')}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
            <h2 id="invitation-title" dir="auto">
                {invitation.public ? (
                    <Link href={`/jobs/${project.id}`}>{project.title}</Link>
                ) : (
                    project.title
                )}
            </h2>
            {'category' in project && project.category && (
                <p className="invitations-meta">
                    <span>{project.category.categoryname}</span>
                    <span>
                        {project.proposals_received === 1
                            ? t('1 proposal')
                            : t(':count proposals', {
                                  count: project.proposals_received,
                              })}
                    </span>
                </p>
            )}
            <div className="invitations-person">
                <Face person={person} size={44} />
                <div>
                    <strong>
                        <bdi>{person.name}</bdi>
                    </strong>
                    <span className="invitations-person-line">
                        {/* The separator dot belongs to the wrapper, outside the isolated text. */}
                        {(person.headline ?? person.company) && (
                            <span>
                                <bdi>{person.headline ?? person.company}</bdi>
                            </span>
                        )}
                        {person.country && <span>{person.country}</span>}
                    </span>
                    <span className="invitations-person-line invitations-person-record">
                        <RatingSummary
                            rating={person.rating}
                            empty={t('No reviews yet')}
                        />
                        <span>
                            <CompletedProjects count={person.completed} />
                        </span>
                    </span>
                </div>
                {sent && person.profile && (
                    <Link
                        className="my-project-link"
                        href={`/freelancers/${person.profile}`}
                    >
                        {t('View profile')}
                    </Link>
                )}
            </div>
            {invitation.public && 'budget_min' in project && (
                <dl className="invitations-facts">
                    <div>
                        <dt>{t('Budget')}</dt>
                        <dd>
                            <Money
                                min={project.budget_min}
                                max={project.budget_max}
                            />
                        </dd>
                    </div>
                    <div>
                        <dt>
                            {invitation.state === 'applied' &&
                            invitation.proposal?.price
                                ? sent
                                    ? t('Their proposal')
                                    : t('Your proposal')
                                : t('Proposals so far')}
                        </dt>
                        <dd>
                            {invitation.state === 'applied' &&
                            invitation.proposal?.price ? (
                                <Money
                                    min={invitation.proposal.price}
                                    max={invitation.proposal.price}
                                />
                            ) : (
                                project.proposals_received
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt>{t('Applications')}</dt>
                        <dd className="invitations-fact-window">
                            <Window project={project} />
                        </dd>
                    </div>
                </dl>
            )}
            {!sent && project.excerpt && (
                <p className="invitations-excerpt" dir="auto">
                    {project.excerpt}
                </p>
            )}
            {'skills' in project && project.skills.length > 0 && (
                <ul className="invitations-skills" aria-label={t('Skills')}>
                    {project.skills.map((skill) => (
                        <li key={skill.id}>{skill.name}</li>
                    ))}
                </ul>
            )}
            <div className="offers-actions invitations-actions">
                {invitation.can_respond && (
                    <InteractiveHoverButton
                        href={`/jobs/${project.id}/apply`}
                        className="offers-main"
                    >
                        {t('Respond with a proposal')}
                    </InteractiveHoverButton>
                )}
                {invitation.state === 'applied' && invitation.proposal && (
                    <InteractiveHoverButton
                        href={`/proposals/${invitation.proposal.id}`}
                        className="offers-main"
                    >
                        {sent
                            ? t('Open the proposal')
                            : t('Open your proposal')}
                    </InteractiveHoverButton>
                )}
                {invitation.can_decline && (
                    <Button
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => setConfirm('decline')}
                    >
                        {t('Decline')}
                    </Button>
                )}
                {invitation.can_resend && (
                    <Button
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => respond('resend')}
                    >
                        {t('Resend invitation')}
                    </Button>
                )}
                {!sent &&
                    invitation.state === 'waiting' &&
                    invitation.public && (
                        <Link
                            className="my-project-link"
                            href={`/jobs/${project.id}`}
                        >
                            {t('Read the full brief')}
                        </Link>
                    )}
            </div>
            {sent &&
                invitation.state === 'declined' &&
                !invitation.can_resend && (
                    <p className="invitations-note">
                        {!project.open
                            ? t(
                                  'The project has closed, so it cannot be sent again.',
                              )
                            : later
                              ? t('You can invite them again :when.', {
                                    when: relative(
                                        invitation.next_resend_at ?? '',
                                    ),
                                })
                              : t(
                                    'This invitation cannot be sent again right now.',
                                )}
                    </p>
                )}
            {sent && invitation.state === 'waiting' && (
                <p className="invitations-note">
                    {t(
                        'An invitation is accepted only when a proposal is submitted.',
                    )}
                </p>
            )}
            <InputError message={form.errors.action ?? form.errors.version} />
            <section
                className="offers-section"
                aria-labelledby="invitation-history"
            >
                <h3 id="invitation-history">{t('Invitation history')}</h3>
                <ol className="invitations-history">
                    {invitation.events.map((event, index) => (
                        <li key={index}>
                            <span>{events[event.kind] ?? event.kind}</span>
                            <FullTime value={event.created_at}>
                                {relative(event.created_at)}
                            </FullTime>
                        </li>
                    ))}
                </ol>
            </section>
            <Dialog
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (!open) setConfirm(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirm === 'block'
                                ? t('Block this account?')
                                : t('Decline this invitation?')}
                        </DialogTitle>
                        <DialogDescription>
                            {confirm === 'block'
                                ? t(
                                      'Block this account? New invitations and proposal contact will be stopped.',
                                  )
                                : t(
                                      'The client will see that you declined. They can invite you again after seven days, and you can still apply while the project is open.',
                                  )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex justify-end gap-3">
                        <Button
                            variant="outline"
                            onClick={() => setConfirm(null)}
                        >
                            {confirm === 'block' ? t('Cancel') : t('Keep it')}
                        </Button>
                        <Button
                            variant="danger"
                            disabled={form.processing}
                            onClick={() =>
                                confirm === 'block'
                                    ? router.post(
                                          `/invitations/${invitation.id}/block`,
                                      )
                                    : respond('decline')
                            }
                        >
                            {confirm === 'block'
                                ? t('Block account')
                                : t('Decline')}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </section>
    );
}

export default function Invitations({
    box,
    list,
    invitations,
    counts,
    other,
    invitation,
    preview,
    readOnly,
}: {
    box: Box;
    list: List;
    invitations: Page<InvitationRow>;
    counts: Record<List, number>;
    // How many invitations the member has in the other box; the Sent / Received switch shows only when there are some.
    other: number;
    invitation: Invitation | null;
    // On /invitations the first invitation is shown beside the list on wide screens; /invitations/{id} is a chosen one.
    preview: boolean;
    // A suspended member reads the invitations; nothing on them can be changed.
    readOnly: boolean;
}) {
    const { t } = useTranslation();
    const reduced = useReducedMotion();
    const { demo } = useDemoSession();
    const sent = box === 'sent';
    const href = (name: List, inBox: Box = box) =>
        `/invitations?box=${inBox}` + (name === 'all' ? '' : `&list=${name}`);
    // A chosen invitation keeps the list's page, so a reload, an answer or the way back stays on it.
    const page =
        invitations.current_page > 1 ? `&page=${invitations.current_page}` : '';
    const query =
        `?box=${box}` + (list === 'all' ? '' : `&list=${list}`) + page;
    const lists: [List, string][] = [
        ['all', t('All')],
        ['waiting', sent ? t('Waiting for them') : t('Waiting for you')],
        ['applied', t('Applied')],
        ['declined', t('Declined')],
        ['closed', t('Closed')],
    ];
    const focus = invitation && !preview ? 'detail' : 'list';
    return (
        <AppLayout
            breadcrumbs={[{ title: t('Invitations'), href: '/invitations' }]}
        >
            <Head title={t('Invitations')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <div className="invitations-head">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {t('Invitations')}
                        </h1>
                        <p className="hiring-lead">
                            {sent
                                ? t(
                                      'Freelancers you asked to apply. They answer with a proposal.',
                                  )
                                : t(
                                      'Clients who asked you to apply. Answer with a proposal, or decline.',
                                  )}
                        </p>
                    </div>
                    <div className="invitations-head-end">
                        {other > 0 && (
                            <nav
                                className="my-projects-lists invitations-boxes"
                                aria-label={t('Invitations')}
                            >
                                {(['sent', 'received'] as Box[]).map((name) => (
                                    <Link
                                        key={name}
                                        href={href('all', name)}
                                        aria-current={
                                            box === name ? 'page' : undefined
                                        }
                                    >
                                        {box === name && (
                                            <span className="my-projects-highlight" />
                                        )}
                                        <span>
                                            {name === 'sent'
                                                ? t('Sent')
                                                : t('Received')}
                                        </span>
                                        <span>
                                            <small>
                                                {box === name
                                                    ? counts.all
                                                    : other}
                                            </small>
                                        </span>
                                    </Link>
                                ))}
                            </nav>
                        )}
                        {!demo && (
                            <Link
                                className="my-project-link"
                                href="/blocked-accounts"
                            >
                                {t('Blocked accounts')}
                            </Link>
                        )}
                    </div>
                </div>
                <div
                    className="offers-page invitations-page"
                    data-focus={focus}
                >
                    <nav
                        className="my-projects-lists"
                        aria-label={t('Invitation lists')}
                    >
                        {lists.map(([name, label]) => (
                            <Link
                                key={name}
                                href={href(name)}
                                aria-current={
                                    list === name ? 'page' : undefined
                                }
                                preserveScroll
                            >
                                {list === name && (
                                    <motion.span
                                        layoutId="invitations-list"
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
                    {counts.all === 0 ? (
                        <div className="jobs-empty">
                            <h2>
                                {sent
                                    ? t('No invitations sent yet.')
                                    : t('No invitations yet.')}
                            </h2>
                            <p>
                                {sent
                                    ? t(
                                          'Invite freelancers from their profiles while a project takes applications.',
                                      )
                                    : t(
                                          'Clients can invite you while your profile is public.',
                                      )}
                            </p>
                            <Link
                                href={sent ? '/freelancers' : '/my-profile'}
                                className="my-project-link offers-empty-link"
                            >
                                {sent
                                    ? t('Find freelancers')
                                    : t('Go to My profile')}
                            </Link>
                        </div>
                    ) : (
                        <div className="offers-inbox">
                            <section
                                className="offers-list"
                                aria-label={t('Your invitations')}
                            >
                                <ul>
                                    {invitations.data.map((row, index) => (
                                        <li key={row.id}>
                                            <BlurFade
                                                delay={
                                                    0.05 +
                                                    Math.min(index, 6) * 0.04
                                                }
                                            >
                                                <Row
                                                    row={row}
                                                    sent={sent}
                                                    selected={
                                                        row.id ===
                                                        invitation?.id
                                                    }
                                                    current={
                                                        row.id ===
                                                            invitation?.id &&
                                                        !preview
                                                    }
                                                    query={query}
                                                />
                                            </BlurFade>
                                        </li>
                                    ))}
                                </ul>
                                {invitations.data.length === 0 && (
                                    <p className="offers-list-empty">
                                        {t('Nothing in this list yet')}
                                    </p>
                                )}
                                {invitations.last_page > 1 && (
                                    <div className="offers-pages">
                                        <Pagination data={invitations} />
                                    </div>
                                )}
                            </section>
                            {invitation && (
                                <BlurFade
                                    delay={0.1}
                                    className="offers-detail-wrap"
                                >
                                    <InvitationDetail
                                        key={invitation.id}
                                        invitation={invitation}
                                        sent={sent}
                                        readOnly={readOnly}
                                        back={href(list) + page}
                                    />
                                </BlurFade>
                            )}
                        </div>
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
