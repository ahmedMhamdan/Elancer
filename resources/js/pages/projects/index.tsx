import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Circle, MoreHorizontal, Plus } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import AppLayout from '@/layouts/app-layout';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AnimatedCircularProgressBar } from '@/components/ui/animated-circular-progress-bar';
import { BlurFade } from '@/components/ui/blur-fade';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { NumberTicker } from '@/components/ui/number-ticker';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import { Money, type Page } from '../discovery/shared';
import { contractStatusLabel } from '../offers/shared';
import '../../../css/elancer-jobs.css';
import '../../../css/elancer-projects.css';

type Face = { name: string | null; avatar: string | null };
type Checklist = Record<
    'title' | 'description' | 'category' | 'skills' | 'budget' | 'cutoff',
    boolean
>;
type Project = {
    id: number;
    title: string | null;
    status: string;
    budget_min: string | null;
    budget_max: string | null;
    published_at: string | null;
    application_closes_at: string | null;
    updated_at: string;
    deleted_at: string | null;
    moderated_at: string | null;
    category: string | null;
    applicants: number;
    waiting: number;
    recent: Face[];
    checklist: Checklist | null;
    hire:
        | (Face & { contract: number; status: string; due: string | null })
        | null;
};
type List = 'all' | 'draft' | 'open' | 'closed' | 'hired' | 'waiting' | 'trash';
type State = 'draft' | 'open' | 'closed' | 'hired' | 'hidden';
const DAY = 86_400_000;

// The cutoff is a time, not a stored status, so a published project is open until that time passes.
function stateOf(project: Project): State {
    if (project.moderated_at) return 'hidden';
    if (project.status === 'draft' || project.status === 'hired')
        return project.status;
    return project.status === 'published' &&
        !!project.application_closes_at &&
        new Date(project.application_closes_at) > new Date()
        ? 'open'
        : 'closed';
}

function Faces({ faces }: { faces: Face[] }) {
    return (
        <span className="my-project-faces" aria-hidden="true">
            {faces.map((face, index) => (
                <span key={index} className="my-project-face">
                    {face.avatar ? (
                        <img src={face.avatar} alt="" loading="lazy" />
                    ) : (
                        (face.name?.trim().charAt(0) ?? '?')
                    )}
                </span>
            ))}
        </span>
    );
}

function ProjectCard({
    project,
    trash,
    readOnly,
    onRemove,
}: {
    project: Project;
    trash: boolean;
    readOnly: boolean;
    onRemove: (project: Project) => void;
}) {
    const { t, ar } = useTranslation();
    const relative = useRelativeTime();
    const state = stateOf(project);
    const labels: Record<State, string> = {
        draft: t('Draft'),
        open: t('Open for applications'),
        closed: t('Applications closed'),
        hired: t('Hired'),
        hidden: t('Hidden by moderation'),
    };
    const when = trash
        ? project.deleted_at &&
          t('Deleted :when', { when: relative(project.deleted_at) })
        : state === 'open' && project.application_closes_at
          ? t('Closes :when', { when: relative(project.application_closes_at) })
          : state === 'closed' && project.application_closes_at
            ? t('Closed :when', {
                  when: relative(project.application_closes_at),
              })
            : state === 'hired' && project.hire?.due
              ? t('Delivery due :when', { when: relative(project.hire.due) })
              : t('Updated :when', { when: relative(project.updated_at) });
    const titleHref =
        trash || (state === 'draft' && readOnly)
            ? null
            : state === 'draft'
              ? `/my-projects/${project.id}/edit`
              : `/jobs/${project.id}`;
    const applicants = `/my-projects/${project.id}/proposals`;
    const canClarify = project.status === 'published' && !project.moderated_at;
    // Day N of M of the application window, for an open project.
    const period =
        state === 'open' &&
        project.published_at &&
        project.application_closes_at
            ? (() => {
                  const start = new Date(project.published_at).getTime();
                  const end = new Date(project.application_closes_at).getTime();
                  const total = Math.max(1, Math.ceil((end - start) / DAY));
                  const day = Math.min(
                      total,
                      Math.max(1, Math.ceil((Date.now() - start) / DAY)),
                  );
                  const share = Math.min(
                      100,
                      Math.max(0, ((Date.now() - start) / (end - start)) * 100),
                  );
                  return { day, total, share };
              })()
            : null;
    const done = project.checklist
        ? Object.values(project.checklist).filter(Boolean).length
        : 0;
    const left = 6 - done;
    const checklist: [keyof Checklist, string][] = [
        ['title', t('Title')],
        ['description', t('Description')],
        ['category', t('Category')],
        ['skills', t('Skills')],
        ['budget', t('Budget')],
        ['cutoff', t('Application cutoff')],
    ];
    const more = (
        items: { label: string; href?: string; onSelect?: () => void }[],
    ) =>
        items.length > 0 && (
            <DropdownMenu dir={ar ? 'rtl' : 'ltr'} modal={false}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="my-project-more"
                        aria-label={t('Project actions')}
                    >
                        <MoreHorizontal className="size-5" aria-hidden="true" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {items.map((item) =>
                        item.href ? (
                            <DropdownMenuItem key={item.label} asChild>
                                <Link href={item.href}>{item.label}</Link>
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                key={item.label}
                                onSelect={item.onSelect}
                            >
                                {item.label}
                            </DropdownMenuItem>
                        ),
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    const view = (
        <Link className="my-project-link" href={`/jobs/${project.id}`}>
            {t('View project')}
        </Link>
    );
    const people = trash ? null : state === 'hired' && project.hire ? (
        <span className="my-project-people">
            <Faces faces={[project.hire]} />
            <span>
                <strong dir="auto">{project.hire.name}</strong>
                {' · '}
                {t('Hired freelancer')}
            </span>
        </span>
    ) : state === 'draft' ? (
        <span className="my-project-people">
            {left === 0
                ? t('Ready to publish')
                : left === 1
                  ? t('1 step left before publishing')
                  : t(':count steps left before publishing', {
                        count: left,
                    })}
        </span>
    ) : (
        <span className="my-project-people">
            {project.recent.length > 0 && <Faces faces={project.recent} />}
            <span>
                <strong>
                    {project.applicants === 0
                        ? t('No applicants yet')
                        : project.applicants === 1
                          ? t('1 applicant')
                          : t(':count applicants', {
                                count: project.applicants,
                            })}
                </strong>
                {project.status === 'published' && project.waiting > 0 && (
                    <>
                        {' · '}
                        {project.waiting === 1
                            ? t('1 waiting for your reply')
                            : t(':count waiting for your reply', {
                                  count: project.waiting,
                              })}
                    </>
                )}
            </span>
        </span>
    );
    const actions = trash ? (
        !readOnly && (
            <>
                <Button
                    variant="outline"
                    onClick={() =>
                        router.post(`/my-projects/${project.id}/restore`)
                    }
                >
                    {t('Restore')}
                </Button>
                <Button
                    variant="danger-outline"
                    onClick={() => onRemove(project)}
                >
                    {t('Delete permanently')}
                </Button>
            </>
        )
    ) : readOnly ? (
        state !== 'draft' && view
    ) : state === 'draft' ? (
        <>
            <InteractiveHoverButton href={`/my-projects/${project.id}/edit`}>
                {t('Continue editing')}
            </InteractiveHoverButton>
            {more([
                {
                    label: t('Move to trash'),
                    onSelect: () => onRemove(project),
                },
            ])}
        </>
    ) : state === 'hired' && project.hire ? (
        <>
            {view}
            <InteractiveHoverButton
                href={`/contracts/${project.hire.contract}`}
            >
                {t('Open the workroom')}
            </InteractiveHoverButton>
            {more([{ label: t('Review applicants'), href: applicants }])}
        </>
    ) : (
        <>
            {view}
            <InteractiveHoverButton href={applicants}>
                {t('Review applicants')}
            </InteractiveHoverButton>
            {more(
                canClarify
                    ? [
                          {
                              label: t('Clarify or extend'),
                              href: `/jobs/${project.id}#project-updates`,
                          },
                      ]
                    : [],
            )}
        </>
    );
    return (
        <article className="my-project">
            <div className="my-project-top">
                <span className="my-project-status" data-state={state}>
                    {labels[state]}
                </span>
                <span>{when}</span>
            </div>
            <div className="my-project-draft">
                <div className="min-w-0">
                    <h2 dir="auto">
                        {titleHref ? (
                            <Link href={titleHref}>
                                {project.title || t('Untitled project')}
                            </Link>
                        ) : (
                            project.title || t('Untitled project')
                        )}
                    </h2>
                    <div className="my-project-meta">
                        {project.category && (
                            <span>
                                <bdi>{project.category}</bdi>
                            </span>
                        )}
                        {project.budget_min && project.budget_max && (
                            <span>
                                <Money
                                    min={project.budget_min}
                                    max={project.budget_max}
                                />
                            </span>
                        )}
                        {state === 'hired' && project.hire && (
                            <span>
                                {contractStatusLabel(project.hire.status, t)}
                            </span>
                        )}
                    </div>
                    {project.checklist && !trash && (
                        <ul className="my-project-checklist">
                            {checklist.map(([key, label]) => (
                                <li
                                    key={key}
                                    data-done={project.checklist![key]}
                                >
                                    {project.checklist![key] ? (
                                        <Check
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <Circle
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                    )}
                                    <span className="sr-only">
                                        {project.checklist![key]
                                            ? t('Done:')
                                            : t('Still needed:')}
                                    </span>
                                    {label}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                {project.checklist && !trash && (
                    <AnimatedCircularProgressBar
                        className="my-project-ring"
                        value={done}
                        max={6}
                        gaugePrimaryColor="var(--el-accent)"
                        gaugeSecondaryColor="var(--el-soft)"
                    >
                        <span
                            role="img"
                            aria-label={t(':percent% ready to publish', {
                                percent: Math.round((done / 6) * 100),
                            })}
                        >
                            {Math.round((done / 6) * 100)}%
                        </span>
                    </AnimatedCircularProgressBar>
                )}
            </div>
            {period && (
                <div className="my-project-window">
                    <span className="my-project-window-bar" aria-hidden="true">
                        <span style={{ width: period.share + '%' }} />
                    </span>
                    <span>
                        {t('Day :day of :total', {
                            day: period.day,
                            total: period.total,
                        })}
                    </span>
                </div>
            )}
            {(people || actions) && (
                <div className="my-project-foot">
                    {people ?? <span />}
                    <div className="my-project-actions">{actions}</div>
                </div>
            )}
        </article>
    );
}

export default function MyProjects({
    projects,
    list,
    counts,
    readOnly,
}: {
    projects: Page<Project>;
    list: List;
    counts: Record<Exclude<List, 'waiting'>, number> & { waiting: number };
    // A suspended client reads the list; nothing on it can be changed.
    readOnly: boolean;
}) {
    const { t } = useTranslation();
    const reduced = useReducedMotion();
    const trash = list === 'trash';
    const [pending, setPending] = useState(false);
    const [remove, setRemove] = useState<Project | null>(null);
    const href = (name: List) =>
        name === 'all' ? '/my-projects' : `/my-projects?status=${name}`;
    function create() {
        setPending(true);
        router.post('/my-projects', {}, { onFinish: () => setPending(false) });
    }
    const tiles: [List, string, number][] = [
        ['draft', t('Drafts'), counts.draft],
        ['open', t('Open for applications'), counts.open],
        ['waiting', t('Waiting for your reply'), counts.waiting],
        ['hired', t('Hired'), counts.hired],
    ];
    const lists: [Exclude<List, 'waiting'>, string][] = [
        ['all', t('All')],
        ['draft', t('Drafts')],
        ['open', t('Open for applications')],
        ['closed', t('Applications closed')],
        ['hired', t('Hired')],
        ['trash', t('Trash')],
    ];
    return (
        <AppLayout
            breadcrumbs={[{ title: t('My projects'), href: '/my-projects' }]}
        >
            <Head title={t('My projects')} />
            <main className="project-editor my-projects w-full">
                <header className="project-editor-header">
                    <div>
                        <h1>{t('My projects')}</h1>
                        <p className="text-muted-foreground">
                            {t(
                                'Shape your brief, publish it, and find the right skills.',
                            )}
                        </p>
                    </div>
                    {!readOnly && (
                        <Button
                            onClick={create}
                            disabled={pending}
                            startIcon={<Plus size={16} />}
                        >
                            {t('Post a project')}
                        </Button>
                    )}
                </header>
                <div className="my-projects-tiles">
                    {tiles.map(([name, label, count], index) => (
                        <BlurFade key={name} delay={index * 0.05}>
                            <Link
                                href={href(name)}
                                className="my-projects-tile"
                                aria-current={
                                    list === name ? 'page' : undefined
                                }
                            >
                                <strong>
                                    <NumberTicker value={count} />
                                </strong>
                                <span>{label}</span>
                            </Link>
                        </BlurFade>
                    ))}
                </div>
                <nav
                    className="my-projects-lists"
                    aria-label={t('Project lists')}
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
                                    layoutId="my-projects-list"
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
                            {name !== 'trash' && (
                                <span>
                                    <small>{counts[name]}</small>
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>
                <div className="my-projects-cards">
                    {projects.data.map((project, index) => (
                        <BlurFade
                            key={project.id}
                            delay={Math.min(index, 6) * 0.05}
                        >
                            <ProjectCard
                                project={project}
                                trash={trash}
                                readOnly={readOnly}
                                onRemove={setRemove}
                            />
                        </BlurFade>
                    ))}
                </div>
                {!projects.data.length && (
                    <div className="jobs-empty">
                        <h2>
                            {t(
                                trash
                                    ? 'Your trash is empty'
                                    : list === 'all'
                                      ? 'Your next project starts here'
                                      : 'Nothing in this list yet',
                            )}
                        </h2>
                        <p>
                            {t(
                                trash
                                    ? 'Deleted drafts will appear here.'
                                    : list === 'all'
                                      ? 'Create a clear brief and tell freelancers what you need.'
                                      : 'Projects appear here as their status changes.',
                            )}
                        </p>
                        {list === 'all' && !readOnly && (
                            <Button onClick={create} disabled={pending}>
                                {t('Create a project')}
                            </Button>
                        )}
                    </div>
                )}
                <Pagination data={projects} />
                <Dialog
                    open={!!remove}
                    onOpenChange={(open) => {
                        if (!open) setRemove(null);
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t(
                                    trash
                                        ? 'Delete this draft permanently?'
                                        : 'Move this draft to trash?',
                                )}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    trash
                                        ? 'This permanently deletes the draft. This cannot be undone.'
                                        : 'You can restore this draft from Trash later.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex justify-end gap-3">
                            <Button
                                variant="outline"
                                onClick={() => setRemove(null)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                variant="danger"
                                disabled={pending}
                                onClick={() => {
                                    if (!remove) return;
                                    setPending(true);
                                    router.delete(
                                        `/my-projects/${remove.id}${trash ? '/permanent' : ''}`,
                                        {
                                            onSuccess: () => setRemove(null),
                                            onFinish: () => setPending(false),
                                        },
                                    );
                                }}
                            >
                                {t(
                                    trash
                                        ? 'Delete permanently'
                                        : 'Move to trash',
                                )}
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            </main>
        </AppLayout>
    );
}
