// Guided form adapts local TailAdmin DefaultInputs.tsx, TextAreaInput.tsx, and MultiSelect.tsx.
// The file control adapts TailAdmin form/input/FileInput.tsx: the same bordered field, with a label
// in place of the browser's own button so its wording follows the interface language.
import { Head, Link, router } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import Button from '@/components/tailadmin/button';
import DatePicker from '@/components/tailadmin/date-picker';
import Input from '@/components/tailadmin/input';
import { DemoOffNote } from '@/components/demo-off';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import { FileSize, Money } from '../discovery/shared';
import type { Attachment, Skill } from '../discovery/shared';
import '../../../css/elancer-jobs.css';

type Project = {
    id: number;
    title: string | null;
    description: string | null;
    category_id: number | null;
    skills: Skill[];
    budget_min: string | null;
    budget_max: string | null;
    application_closes_at: string | null;
    screening_questions: string[] | null;
    version: number;
    status: string;
};
type Draft = {
    title: string;
    description: string;
    category_id: string;
    skills: number[];
    budget_min: string;
    budget_max: string;
    application_closes_at: string;
    screening_questions: string[];
};
function fromProject(p: Project): Draft {
    const date = p.application_closes_at
        ? new Date(p.application_closes_at)
        : null;
    const local = date
        ? new Date(date.getTime() - date.getTimezoneOffset() * 60000)
              .toISOString()
              .slice(0, 16)
        : '';
    return {
        title: p.title ?? '',
        description: p.description ?? '',
        category_id: p.category_id ? String(p.category_id) : '',
        skills: p.skills.map((s) => s.id),
        budget_min: p.budget_min ?? '',
        budget_max: p.budget_max ?? '',
        application_closes_at: local,
        screening_questions: p.screening_questions ?? [],
    };
}
function csrf() {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice(11) ?? '',
    );
}
// Q11: what the server accepts on a brief. It checks again; these only save a wasted upload.
const FILE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt'];
const FILE_BYTES = 5 * 1024 * 1024;
export default function EditProject({
    project,
    categories,
    skills,
    attachments,
    attachmentLimit,
}: {
    project: Project;
    categories: { id: number; categoryname: string }[];
    skills: Skill[];
    attachments: Attachment[];
    attachmentLimit: number;
}) {
    const { t } = useTranslation();
    // Plan 032 part 10: no file enters storage through the shared demo account.
    const { demo } = useDemoSession();
    const [form, setForm] = useState(() => fromProject(project));
    // Files are saved apart from the text: neither waits for the other, and neither's failure is the other's.
    const [files, setFiles] = useState(attachments);
    const [fileBusy, setFileBusy] = useState(false);
    const [fileNote, setFileNote] = useState<{
        text: string;
        error: boolean;
    } | null>(null);
    const picker = useRef<HTMLInputElement>(null);
    const [step, setStep] = useState(0);
    const [status, setStatus] = useState('Saved');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [conflict, setConflict] = useState<Project | null>(null);
    const [busy, setBusy] = useState(false);
    const [skillQuery, setSkillQuery] = useState('');
    const [range, setRange] = useState(
        project.budget_min !== project.budget_max,
    );
    const [reviewed, setReviewed] = useState(false);
    const current = useRef(form);
    current.current = form;
    const saved = useRef(JSON.stringify(form));
    const version = useRef(project.version);
    const paused = useRef(false);
    const attempted = useRef('');
    const flight = useRef<Promise<boolean> | null>(null);
    const mounted = useRef(true);
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const send = useCallback(
        (publish = false): Promise<boolean> => {
            if (flight.current) return flight.current;
            if (paused.current) return Promise.resolve(false);
            const snapshot = structuredClone(current.current);
            const serialized = JSON.stringify(snapshot);
            if (!publish && serialized === saved.current)
                return Promise.resolve(true);
            attempted.current = serialized;
            setBusy(true);
            setStatus('Saving…');
            setErrors({});
            const request = (async () => {
                try {
                    const cutoff = snapshot.application_closes_at
                        ? new Date(snapshot.application_closes_at)
                        : null;
                    const response = await fetch(
                        `/my-projects/${project.id}${publish ? '/publish' : ''}`,
                        {
                            method: publish ? 'POST' : 'PATCH',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-XSRF-TOKEN': csrf(),
                            },
                            body: JSON.stringify({
                                ...snapshot,
                                category_id: snapshot.category_id || null,
                                budget_min: snapshot.budget_min || null,
                                budget_max: snapshot.budget_max || null,
                                application_closes_at:
                                    cutoff && !Number.isNaN(cutoff.getTime())
                                        ? cutoff.toISOString()
                                        : null,
                                version: version.current,
                            }),
                        },
                    );
                    if (!mounted.current) return false;
                    if (response.status === 419 || response.status === 401) {
                        paused.current = true;
                        setStatus('Sign in again to save');
                        return false;
                    }
                    const data = await response.json();
                    if (response.status === 409) {
                        paused.current = true;
                        setConflict(data.project);
                        setStatus('Another tab changed this draft');
                        return false;
                    }
                    if (!response.ok) {
                        setErrors(data.errors ?? {});
                        setStatus('Changes could not be saved');
                        return false;
                    }
                    version.current = data.project.version;
                    saved.current = serialized;
                    setStatus(
                        serialized === JSON.stringify(current.current)
                            ? 'Saved'
                            : 'Unsaved changes',
                    );
                    if (publish && data.url) router.visit(data.url);
                    return true;
                } catch {
                    if (mounted.current)
                        setStatus('Changes could not be saved');
                    return false;
                } finally {
                    flight.current = null;
                    if (mounted.current) setBusy(false);
                }
            })();
            flight.current = request;
            return request;
        },
        [project.id],
    );
    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
        };
    }, []);
    useEffect(() => {
        if (
            paused.current ||
            busy ||
            JSON.stringify(form) === saved.current ||
            JSON.stringify(form) === attempted.current
        )
            return;
        const timer = setTimeout(() => {
            void send();
        }, 750);
        return () => clearTimeout(timer);
    }, [form, busy, send]);
    useEffect(() => {
        const retry = () => {
            void send();
        };
        window.addEventListener('online', retry);
        return () => window.removeEventListener('online', retry);
    }, [send]);
    useEffect(() => {
        const dirty = () => JSON.stringify(current.current) !== saved.current;
        const unload = (event: BeforeUnloadEvent) => {
            if (dirty()) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', unload);
        const stop = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                dirty() &&
                !window.confirm(t('Leave with unsaved changes?'))
            )
                return false;
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            stop();
        };
    }, [t]);
    async function fileRequest(
        method: 'POST' | 'DELETE',
        path: string,
        body: FormData | null,
        done: string,
    ) {
        setFileBusy(true);
        try {
            const response = await fetch(
                `/my-projects/${project.id}/attachments${path}`,
                {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrf(),
                    },
                    body,
                },
            );
            if (!mounted.current) return;
            const data = await response.json().catch(() => null);
            if (response.ok && data?.attachments) {
                setFiles(data.attachments);
                setReviewed(false);
                setFileNote({ text: done, error: false });
                return;
            }
            setFileNote({
                error: true,
                text:
                    response.status === 419 || response.status === 401
                        ? t(
                              'Your session expired. Sign in in another tab, then retry here.',
                          )
                        : response.status === 413
                          ? t(
                                'This file could not be uploaded. It may be larger than this server accepts.',
                            )
                          : (data?.errors?.file?.[0] ??
                            data?.message ??
                            t(
                                'The file change did not go through. Your text is not affected.',
                            )),
            });
        } catch {
            if (mounted.current)
                setFileNote({
                    error: true,
                    text: t(
                        'The file change did not go through. Check your connection and try again.',
                    ),
                });
        } finally {
            if (mounted.current) setFileBusy(false);
        }
    }
    function attach(file: File | undefined) {
        if (picker.current) picker.current.value = '';
        if (!file) return;
        const ending = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!FILE_TYPES.includes(ending))
            return setFileNote({
                error: true,
                text: t('Attach a JPG, PNG, WebP, PDF or TXT file.'),
            });
        if (file.size > FILE_BYTES)
            return setFileNote({
                error: true,
                text: t('Each file can be up to 5 MB.'),
            });
        const body = new FormData();
        body.append('file', file);
        setFileNote({
            error: false,
            text: t('Uploading :name…', { name: file.name }),
        });
        void fileRequest(
            'POST',
            '',
            body,
            t(':name was added.', { name: file.name }),
        );
    }
    function detach(file: Attachment) {
        if (
            !window.confirm(
                t('Remove :name from this brief?', { name: file.name }),
            )
        )
            return;
        void fileRequest(
            'DELETE',
            `/${file.id}`,
            null,
            t(':name was removed.', { name: file.name }),
        );
    }
    function change<K extends keyof Draft>(key: K, value: Draft[K]) {
        setReviewed(false);
        setStatus('Unsaved changes');
        setForm((d) => ({ ...d, [key]: value }));
    }
    async function flush(exit = false, publish = false) {
        if (flight.current && !(await flight.current)) return;
        if (!(await send(publish))) return;
        if (JSON.stringify(current.current) !== saved.current && !publish) {
            if (!(await send())) return;
        }
        if (exit) router.visit('/my-projects');
    }
    const names: Record<keyof Draft, string> = {
        title: t('Project title'),
        description: t('Project brief'),
        category_id: t('Category'),
        skills: t('Required skills'),
        budget_min: t('Minimum budget'),
        budget_max: t('Maximum budget'),
        application_closes_at: t('Application cutoff'),
        screening_questions: t('Screening questions'),
    };
    function display(key: keyof Draft, data: Draft) {
        if (key === 'category_id')
            return (
                categories.find((c) => String(c.id) === data.category_id)
                    ?.categoryname ?? data.category_id
            );
        if (key === 'skills')
            return data.skills
                .map((id) => skills.find((s) => s.id === id)?.name ?? id)
                .join(', ');
        return Array.isArray(data[key]) ? data[key].join('\n') : data[key];
    }
    const errorSummary = Object.entries(errors);
    return (
        <AppLayout
            breadcrumbs={[
                { title: t('My projects'), href: '/my-projects' },
                {
                    title: t('Edit project'),
                    href: `/my-projects/${project.id}/edit`,
                },
            ]}
        >
            <Head title={t('Create your project')} />
            <main className="project-editor w-full">
                <header className="project-editor-header">
                    <div>
                        <h1>{t('Create your project')}</h1>
                        <p className="text-muted-foreground">
                            {t(
                                'A clear brief is the start of a great collaboration.',
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <span aria-live="polite" className="text-sm">
                            {t(status)}
                        </span>
                        <Button
                            variant="outline"
                            disabled={busy || fileBusy || !!conflict}
                            onClick={() => void flush(true)}
                        >
                            {t('Save and exit')}
                        </Button>
                    </div>
                </header>
                {status === 'Changes could not be saved' && (
                    <div role="alert" className="project-errors">
                        <p>
                            {t(
                                'Your edits are still here. Check your connection and try again.',
                            )}
                        </p>
                        <Button onClick={() => void send()} disabled={busy}>
                            {t('Retry save')}
                        </Button>
                    </div>
                )}
                {status === 'Sign in again to save' && (
                    <div role="alert" className="project-errors">
                        <p>
                            {t(
                                'Your session expired. Sign in in another tab, then retry here.',
                            )}
                        </p>
                        <a href="/login" target="_blank" rel="noreferrer">
                            {t('Sign in')}
                        </a>
                        <Button
                            onClick={() => {
                                paused.current = false;
                                void send();
                            }}
                        >
                            {t('Retry save')}
                        </Button>
                    </div>
                )}
                {conflict && (
                    <section className="project-conflict">
                        <h2>{t('Another tab changed this draft')}</h2>
                        <p>
                            {t(
                                'Compare both versions before choosing which one to keep.',
                            )}
                        </p>
                        {(Object.keys(names) as (keyof Draft)[])
                            .filter(
                                (key) =>
                                    JSON.stringify(form[key]) !==
                                    JSON.stringify(fromProject(conflict)[key]),
                            )
                            .map((key) => (
                                <div key={key} className="my-4">
                                    <h3>{names[key]}</h3>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Your version')}
                                    </p>
                                    <pre dir="auto">{display(key, form)}</pre>
                                    <p className="text-muted-foreground text-sm">
                                        {t('Saved version')}
                                    </p>
                                    <pre dir="auto">
                                        {display(key, fromProject(conflict))}
                                    </pre>
                                </div>
                            ))}
                        {conflict.status === 'draft' ? (
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    onClick={() => {
                                        version.current = conflict.version;
                                        paused.current = false;
                                        setConflict(null);
                                        void send();
                                    }}
                                >
                                    {t('Keep my version')}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        const next = fromProject(conflict);
                                        current.current = next;
                                        saved.current = JSON.stringify(next);
                                        version.current = conflict.version;
                                        setForm(next);
                                        paused.current = false;
                                        setConflict(null);
                                        setStatus('Saved');
                                    }}
                                >
                                    {t('Use saved version')}
                                </Button>
                            </div>
                        ) : (
                            <Link href={`/jobs/${project.id}`}>
                                {t('View published project')}
                            </Link>
                        )}
                    </section>
                )}
                {!!errorSummary.length && (
                    <div role="alert" className="project-errors">
                        <h2>{t('Please check these fields')}</h2>
                        <ul>
                            {errorSummary.map(([key, values]) => (
                                <li key={key}>{values.join(' ')}</li>
                            ))}
                        </ul>
                    </div>
                )}
                <nav
                    aria-label={t('Project setup steps')}
                    className="project-steps"
                >
                    {[
                        'Brief',
                        'Requirements',
                        'Budget and cutoff',
                        'Review',
                    ].map((label, index) => (
                        <button
                            key={label}
                            type="button"
                            aria-current={step === index ? 'step' : undefined}
                            onClick={() => setStep(index)}
                        >
                            {index + 1}. {t(label)}
                        </button>
                    ))}
                </nav>
                <section className="job-card project-fields">
                    {step === 0 && (
                        <>
                            <div>
                                <label htmlFor="project-title">
                                    {t('Project title')}
                                </label>
                                <Input
                                    id="project-title"
                                    dir="auto"
                                    value={form.title}
                                    maxLength={160}
                                    onChange={(e) =>
                                        change('title', e.target.value)
                                    }
                                    placeholder={t(
                                        'Give your project a clear, specific title',
                                    )}
                                />
                            </div>
                            <div>
                                <label htmlFor="project-description">
                                    {t('Project brief')}
                                </label>
                                <textarea
                                    id="project-description"
                                    dir="auto"
                                    value={form.description}
                                    maxLength={20000}
                                    onChange={(e) =>
                                        change('description', e.target.value)
                                    }
                                />
                                <small>
                                    {t(
                                        'Describe the goal, expected deliverables, and anything freelancers should know.',
                                    )}
                                </small>
                            </div>
                            <div>
                                <label htmlFor="project-category">
                                    {t('Category')}
                                </label>
                                <select
                                    id="project-category"
                                    value={form.category_id}
                                    onChange={(e) =>
                                        change('category_id', e.target.value)
                                    }
                                >
                                    <option value="">
                                        {t('Choose a category')}
                                    </option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.categoryname}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <fieldset className="project-attachments">
                                <legend>
                                    {t('Attached files (optional)')}
                                </legend>
                                {demo ? (
                                    <DemoOffNote>
                                        {t(
                                            'Files are switched off in the shared demo.',
                                        )}
                                    </DemoOffNote>
                                ) : (
                                    <>
                                        {/* Q11: who will see the files is said before the control, not after an upload. */}
                                        <p id="project-attachments-audience">
                                            {t(
                                                'Everyone can see how many files a brief carries. Only signed-in members with an active account and a verified email can download them, so attach nothing private.',
                                            )}
                                        </p>
                                        {files.length > 0 && (
                                            <ul>
                                                {files.map((file) => (
                                                    <li key={file.id}>
                                                        <Paperclip
                                                            size={16}
                                                            aria-hidden="true"
                                                        />
                                                        <a
                                                            href={`/jobs/${project.id}/attachments/${file.id}`}
                                                            download
                                                        >
                                                            <bdi>
                                                                {file.name}
                                                            </bdi>
                                                        </a>
                                                        <span className="job-muted">
                                                            <FileSize
                                                                bytes={
                                                                    file.size
                                                                }
                                                            />
                                                        </span>
                                                        <Button
                                                            variant="outline"
                                                            disabled={
                                                                fileBusy ||
                                                                !!conflict
                                                            }
                                                            aria-label={t(
                                                                'Remove :name',
                                                                {
                                                                    name: file.name,
                                                                },
                                                            )}
                                                            onClick={() =>
                                                                detach(file)
                                                            }
                                                        >
                                                            {t('Remove')}
                                                        </Button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        <div className="project-file-field">
                                            <input
                                                ref={picker}
                                                id="project-attachment"
                                                type="file"
                                                className="sr-only"
                                                accept={FILE_TYPES.map(
                                                    (type) => `.${type}`,
                                                ).join(',')}
                                                disabled={
                                                    fileBusy ||
                                                    !!conflict ||
                                                    files.length >=
                                                        attachmentLimit
                                                }
                                                aria-describedby="project-attachments-audience project-attachments-rules"
                                                onChange={(e) =>
                                                    attach(e.target.files?.[0])
                                                }
                                            />
                                            <label htmlFor="project-attachment">
                                                {t('Choose a file')}
                                            </label>
                                            <span>
                                                {t(
                                                    ':count of :total files attached',
                                                    {
                                                        count: files.length,
                                                        total: attachmentLimit,
                                                    },
                                                )}
                                            </span>
                                        </div>
                                        <small id="project-attachments-rules">
                                            {t(
                                                'Up to :count files, 5 MB each: JPG, PNG, WebP, PDF or TXT. Files cannot be added or removed after you publish.',
                                                { count: attachmentLimit },
                                            )}
                                        </small>
                                        <p
                                            role={
                                                fileNote?.error
                                                    ? 'alert'
                                                    : 'status'
                                            }
                                            className={
                                                fileNote?.error
                                                    ? 'project-file-note project-file-error'
                                                    : 'project-file-note'
                                            }
                                        >
                                            {fileNote?.text}
                                        </p>
                                    </>
                                )}
                            </fieldset>
                        </>
                    )}
                    {step === 1 && (
                        <>
                            <fieldset>
                                <legend>{t('Required skills')}</legend>
                                <label
                                    className="sr-only"
                                    htmlFor="project-skill-search"
                                >
                                    {t('Search skills')}
                                </label>
                                <Input
                                    id="project-skill-search"
                                    value={skillQuery}
                                    onChange={(e) =>
                                        setSkillQuery(e.target.value)
                                    }
                                    placeholder={t('Search skills')}
                                />
                                <div className="job-skill-options">
                                    {skills
                                        .filter((s) =>
                                            s.name
                                                .toLocaleLowerCase()
                                                .includes(
                                                    skillQuery.toLocaleLowerCase(),
                                                ),
                                        )
                                        .map((s) => (
                                            <label key={s.id}>
                                                <input
                                                    type="checkbox"
                                                    checked={form.skills.includes(
                                                        s.id,
                                                    )}
                                                    disabled={
                                                        !form.skills.includes(
                                                            s.id,
                                                        ) &&
                                                        form.skills.length >= 15
                                                    }
                                                    onChange={(e) =>
                                                        change(
                                                            'skills',
                                                            e.target.checked
                                                                ? [
                                                                      ...form.skills,
                                                                      s.id,
                                                                  ]
                                                                : form.skills.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          s.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                <bdi>{s.name}</bdi>
                                            </label>
                                        ))}
                                </div>
                                <small>
                                    {t('Choose between 1 and 15 skills.')}
                                </small>
                            </fieldset>
                            <div>
                                <h2>{t('Screening questions')}</h2>
                                <p>
                                    {t(
                                        'Add up to three questions for freelancers to answer when applying.',
                                    )}
                                </p>
                                {form.screening_questions.map(
                                    (question, index) => (
                                        <div
                                            key={index}
                                            className="project-question"
                                        >
                                            <label
                                                className="sr-only"
                                                htmlFor={`question-${index}`}
                                            >
                                                {t('Question :number', {
                                                    number: index + 1,
                                                })}
                                            </label>
                                            <Input
                                                id={`question-${index}`}
                                                dir="auto"
                                                value={question}
                                                maxLength={300}
                                                onChange={(e) =>
                                                    change(
                                                        'screening_questions',
                                                        form.screening_questions.map(
                                                            (q, i) =>
                                                                i === index
                                                                    ? e.target
                                                                          .value
                                                                    : q,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    change(
                                                        'screening_questions',
                                                        form.screening_questions.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                {t('Remove')}
                                            </Button>
                                        </div>
                                    ),
                                )}
                                <Button
                                    variant="outline"
                                    disabled={
                                        form.screening_questions.length >= 3
                                    }
                                    onClick={() =>
                                        change('screening_questions', [
                                            ...form.screening_questions,
                                            '',
                                        ])
                                    }
                                >
                                    {t('Add a question')}
                                </Button>
                            </div>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <fieldset>
                                <legend>{t('Budget (USD)')}</legend>
                                <label>
                                    <input
                                        type="checkbox"
                                        checked={range}
                                        onChange={(e) => {
                                            setRange(e.target.checked);
                                            if (!e.target.checked)
                                                change(
                                                    'budget_max',
                                                    form.budget_min,
                                                );
                                        }}
                                    />{' '}
                                    {t('Use a budget range')}
                                </label>
                                <div className="job-budget-fields">
                                    <div>
                                        <label htmlFor="project-budget-min">
                                            {t(
                                                range
                                                    ? 'Minimum budget'
                                                    : 'Target budget',
                                            )}
                                        </label>
                                        <Input
                                            id="project-budget-min"
                                            type="number"
                                            min="1"
                                            max="1000000"
                                            step={0.01}
                                            value={form.budget_min}
                                            onChange={(e) => {
                                                const value = e.target.value;
                                                setReviewed(false);
                                                setForm((d) => ({
                                                    ...d,
                                                    budget_min: value,
                                                    budget_max: range
                                                        ? d.budget_max
                                                        : value,
                                                }));
                                            }}
                                        />
                                    </div>
                                    {range && (
                                        <div>
                                            <label htmlFor="project-budget-max">
                                                {t('Maximum budget')}
                                            </label>
                                            <Input
                                                id="project-budget-max"
                                                type="number"
                                                min="1"
                                                max="1000000"
                                                step={0.01}
                                                value={form.budget_max}
                                                onChange={(e) =>
                                                    change(
                                                        'budget_max',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                                <small>
                                    {t(
                                        'The advertised budget is a guide. Final terms are agreed with the freelancer.',
                                    )}
                                </small>
                            </fieldset>
                            <div>
                                <label htmlFor="project-cutoff">
                                    {t('Application cutoff')}
                                </label>
                                <DatePicker
                                    id="project-cutoff"
                                    time
                                    min="today"
                                    placeholder={t('Choose a date')}
                                    value={form.application_closes_at}
                                    onChange={(value) =>
                                        change('application_closes_at', value)
                                    }
                                />
                                <small>
                                    {t('Times shown in :timezone', {
                                        timezone,
                                    })}
                                </small>
                                <small>
                                    {t(
                                        'This is the deadline to apply, not the delivery date.',
                                    )}
                                </small>
                            </div>
                        </>
                    )}
                    {step === 3 && (
                        <>
                            <h2>{t('Review your project')}</h2>
                            <h3 dir="auto">
                                {form.title || t('Untitled project')}
                            </h3>
                            <p className="job-description" dir="auto">
                                {form.description || t('No description yet')}
                            </p>
                            <dl className="grid gap-4">
                                {(
                                    [
                                        'category_id',
                                        'skills',
                                        'application_closes_at',
                                        'screening_questions',
                                    ] as (keyof Draft)[]
                                ).map((key) => (
                                    <div key={key}>
                                        <dt className="text-muted-foreground">
                                            {names[key]}
                                        </dt>
                                        <dd
                                            dir="auto"
                                            className="whitespace-pre-wrap"
                                        >
                                            {display(key, form) ||
                                                t('Not added yet')}
                                        </dd>
                                    </div>
                                ))}
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('Attached files')}
                                    </dt>
                                    <dd>
                                        {files.length > 0 ? (
                                            <ul className="project-review-files">
                                                {files.map((file) => (
                                                    <li key={file.id}>
                                                        <bdi>{file.name}</bdi>{' '}
                                                        <span className="job-muted">
                                                            <FileSize
                                                                bytes={
                                                                    file.size
                                                                }
                                                            />
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            t('Not added yet')
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            {form.budget_min && form.budget_max && (
                                <Money
                                    min={form.budget_min}
                                    max={form.budget_max}
                                />
                            )}
                            <p>{t('Times shown in :timezone', { timezone })}</p>
                            <p>
                                {t(
                                    'Publishing makes your brief visible to everyone. Do not include private contact details.',
                                )}
                            </p>
                            {files.length > 0 && (
                                <p>
                                    {t(
                                        'The number of attached files will be public. Signed-in members with an active account and a verified email will be able to download them, and they cannot be added or removed after you publish.',
                                    )}
                                </p>
                            )}
                            <label className="flex items-start gap-3">
                                <input
                                    className="mt-1"
                                    type="checkbox"
                                    checked={reviewed}
                                    onChange={(e) =>
                                        setReviewed(e.target.checked)
                                    }
                                />
                                {t(
                                    'I reviewed the brief, budget, and application cutoff.',
                                )}
                            </label>
                            <Button
                                disabled={
                                    busy || fileBusy || !reviewed || !!conflict
                                }
                                onClick={() => void flush(false, true)}
                            >
                                {t('Publish project')}
                            </Button>
                        </>
                    )}
                </section>
                <div className="project-editor-actions">
                    <Button
                        variant="outline"
                        disabled={step === 0}
                        onClick={() => setStep((s) => s - 1)}
                    >
                        {t('Back')}
                    </Button>
                    {step < 3 && (
                        <Button onClick={() => setStep((s) => s + 1)}>
                            {t('Continue')}
                        </Button>
                    )}
                </div>
                <p className="text-muted-foreground mt-5 text-sm">
                    {t('Drafts are private. Your changes save automatically.')}
                </p>
            </main>
        </AppLayout>
    );
}
