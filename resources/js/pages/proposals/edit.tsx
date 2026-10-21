// The file control adapts TailAdmin form/input/FileInput.tsx as the brief editor does (plan 030).
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    AttachedFiles,
    FILE_ACCEPT,
    useFileProblem,
} from '@/components/attached-files';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import TextArea from '@/components/tailadmin/textarea';
import { DemoOffNote } from '@/components/demo-off';
import HelpLink from '@/components/help-link';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import type { WorkLink } from '@/components/freelancer-card';
import type { Attachment } from '@/pages/discovery/shared';
import {
    ProposalLayout,
    ProjectContext,
    type Proposal,
    type ProposalProject,
} from './shared';

type Draft = {
    price: string;
    duration_days: string;
    message: string;
    answers: string[];
    samples: WorkLink[];
    cases: number[];
};
// Plan 031: the member's case studies; only public ones can be sent.
type CaseOption = {
    id: number;
    title: string;
    summary: string;
    elancer_work: boolean;
    shareable: boolean;
};
export default function Edit({
    project,
    proposal,
    profilePublished,
    sampleLinks,
    caseOptions,
    caseLimit,
    fileLimit,
    trial,
}: {
    project: ProposalProject;
    proposal: Proposal | null;
    profilePublished: boolean;
    sampleLinks: WorkLink[];
    caseOptions: CaseOption[];
    caseLimit: number;
    fileLimit: number;
    // Plan 027: a member trying out a demo project.
    trial: boolean;
}) {
    const { t } = useTranslation();
    // Plan 032 part 10: no file enters storage through the shared demo account.
    const { demo } = useDemoSession();
    const initial = proposal?.draft ?? proposal?.content;
    const [data, setData] = useState<Draft>({
        price: initial?.price ?? '',
        duration_days: String(initial?.duration_days ?? ''),
        message: initial?.message ?? '',
        answers: (project.screening_questions ?? []).map(
            (_, i) => initial?.answers[i] ?? '',
        ),
        samples: initial?.samples ?? [],
        cases: (initial?.cases ?? []).filter((id) =>
            caseOptions.some((option) => option.id === id),
        ),
    });
    const [busy, setBusy] = useState(false);
    const [saved, setSaved] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [conflict, setConflict] = useState(false);
    const version = useRef(proposal?.version ?? 0);
    // The first save makes the proposal, so a file's address is known from then on.
    const [proposalId, setProposalId] = useState(proposal?.id ?? null);
    const inFlight = useRef(false);
    const lastSaved = useRef(JSON.stringify(data));
    const latest = useRef(data);
    latest.current = data;
    const blocked = useRef(false);
    const save = useCallback(
        async (action: 'save' | 'submit') => {
            if (inFlight.current || blocked.current) return;
            inFlight.current = true;
            setBusy(true);
            setErrors([]);
            setSaved(false);
            const payload = latest.current;
            try {
                const token =
                    document.cookie
                        .split('; ')
                        .find((v) => v.startsWith('XSRF-TOKEN='))
                        ?.slice(11) ?? '';
                const response = await fetch(`/jobs/${project.id}/proposal`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(token),
                    },
                    body: JSON.stringify({
                        ...payload,
                        version: version.current,
                        action,
                    }),
                });
                const result = await response.json();
                if (response.status === 409) {
                    blocked.current = true;
                    setConflict(true);
                    return;
                }
                if (!response.ok) {
                    setErrors(
                        result.errors
                            ? (Object.values(result.errors).flat() as string[])
                            : [
                                  result.message ??
                                      t('Unable to save. Please try again.'),
                              ],
                    );
                    return;
                }
                version.current = result.proposal.version;
                setProposalId(result.proposal.id);
                lastSaved.current = JSON.stringify(payload);
                setSaved(true);
                if (action === 'submit') {
                    blocked.current = true;
                    router.visit(result.url);
                }
            } catch {
                setErrors([t('Unable to save. Please try again.')]);
            } finally {
                inFlight.current = false;
                setBusy(false);
            }
        },
        [project.id, t],
    );
    useEffect(() => {
        if (
            JSON.stringify(data) === lastSaved.current ||
            conflict ||
            errors.length > 0 ||
            busy
        )
            return;
        const timer = window.setTimeout(() => {
            void save('save');
        }, 1500);
        return () => window.clearTimeout(timer);
    }, [data, conflict, save, busy, errors]);
    useEffect(() => {
        const warn = (event: BeforeUnloadEvent) => {
            if (JSON.stringify(latest.current) !== lastSaved.current)
                event.preventDefault();
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, []);
    // Files are saved apart from the text: neither waits for the other, and neither's failure is the other's (Q71).
    const [files, setFiles] = useState<Attachment[]>(proposal?.files ?? []);
    const [fileBusy, setFileBusy] = useState(false);
    const [fileNote, setFileNote] = useState<{
        text: string;
        error: boolean;
    } | null>(null);
    const picker = useRef<HTMLInputElement>(null);
    const mounted = useRef(true);
    useEffect(
        () => () => {
            mounted.current = false;
        },
        [],
    );
    const fileProblem = useFileProblem();
    async function fileRequest(
        method: 'POST' | 'DELETE',
        path: string,
        body: FormData | null,
        done: string,
    ) {
        setFileBusy(true);
        try {
            const token =
                document.cookie
                    .split('; ')
                    .find((v) => v.startsWith('XSRF-TOKEN='))
                    ?.slice(11) ?? '';
            const response = await fetch(
                `/jobs/${project.id}/proposal/files${path}`,
                {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(token),
                    },
                    body,
                },
            );
            if (!mounted.current) return;
            const result = await response.json().catch(() => null);
            if (response.ok && result?.files) {
                setFiles(result.files);
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
                          : (result?.errors?.file?.[0] ??
                            result?.message ??
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
    async function attach(file: File | undefined) {
        if (picker.current) picker.current.value = '';
        if (!file) return;
        const problem = fileProblem(file);
        if (problem) return setFileNote({ error: true, text: problem });
        setFileNote({
            error: false,
            text: t('Uploading :name…', { name: file.name }),
        });
        // A file belongs to a saved draft, so the first one waits for the draft to be saved.
        if (version.current === 0) await save('save');
        if (!mounted.current) return;
        if (version.current === 0)
            return setFileNote({
                error: true,
                text: t('Save your draft before attaching files.'),
            });
        const body = new FormData();
        body.append('file', file);
        await fileRequest(
            'POST',
            '',
            body,
            t(':name was added.', { name: file.name }),
        );
    }
    function detach(file: Attachment) {
        if (
            !window.confirm(
                t('Remove :name from this proposal?', { name: file.name }),
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
    const update = <K extends keyof Draft>(key: K, value: Draft[K]) => {
        setSaved(false);
        setErrors([]);
        setData((v) => ({ ...v, [key]: value }));
    };
    const eligible =
        profilePublished ||
        ['submitted', 'reopened'].includes(proposal?.status ?? '');
    return (
        <ProposalLayout title={t('Your proposal')}>
            <Head title={t('Your proposal')} />
            <div className="market-two-column">
                <form
                    className="market-panel market-stack"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void save('submit');
                    }}
                >
                    <p className="market-muted" data-test="proposal-note">
                        {t(
                            trial
                                ? 'This is a sample project from the demo. You can apply to see how applying works, but no real client will read or answer your proposal.'
                                : 'Drafts are private. Only submitting shares your proposal with the client.',
                        )}
                    </p>
                    <HelpLink
                        article={trial ? 'sample-projects' : 'applying'}
                    />
                    {!eligible && (
                        <p className="market-error">
                            <Link href="/my-profile">
                                {t(
                                    'Publish your freelancer profile before applying.',
                                )}
                            </Link>
                        </p>
                    )}
                    {conflict && (
                        <div role="alert" className="market-error">
                            <p>
                                {t(
                                    'This proposal changed in another tab or is no longer editable. Copy any unsaved text, then reload.',
                                )}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.location.reload()}
                            >
                                {t('Reload latest version')}
                            </Button>
                        </div>
                    )}
                    {!!errors.length && (
                        <div role="alert" className="market-error">
                            {errors.map((error, i) => (
                                <p key={i}>{error}</p>
                            ))}
                        </div>
                    )}
                    <div className="proposal-terms">
                        <label className="market-field">
                            <span>{t('Proposed price (USD)')}</span>
                            <Input
                                type="number"
                                min="1"
                                max="1000000"
                                step={0.01}
                                value={data.price}
                                onChange={(e) =>
                                    update('price', e.target.value)
                                }
                            />
                        </label>
                        <label className="market-field">
                            <span>
                                {t('Delivery duration (calendar days)')}
                            </span>
                            <Input
                                type="number"
                                min="1"
                                max="3650"
                                step={1}
                                value={data.duration_days}
                                onChange={(e) =>
                                    update('duration_days', e.target.value)
                                }
                            />
                        </label>
                    </div>
                    <label className="market-field">
                        <span>{t('Cover message')}</span>
                        <TextArea
                            rows={8}
                            maxLength={10000}
                            value={data.message}
                            onChange={(value) => update('message', value)}
                            hint={t(
                                'Explain your approach and relevant experience. At least 20 characters.',
                            )}
                        />
                    </label>
                    {(project.screening_questions ?? []).map((question, i) => (
                        <label className="market-field" key={i}>
                            <span dir="auto">{question}</span>
                            <TextArea
                                rows={3}
                                maxLength={3000}
                                value={data.answers[i]}
                                onChange={(value) =>
                                    update(
                                        'answers',
                                        data.answers.map((v, j) =>
                                            j === i ? value : v,
                                        ),
                                    )
                                }
                            />
                        </label>
                    ))}
                    <h2>{t('Work samples')}</h2>
                    <p className="market-muted">
                        {t(
                            'Add up to three HTTPS links to relevant work you are allowed to share.',
                        )}
                    </p>
                    {data.samples.map((sample, i) => (
                        <div className="market-link-editor" key={i}>
                            <label className="market-field">
                                <span>{t('Link label')}</span>
                                <Input
                                    value={sample.label}
                                    maxLength={80}
                                    onChange={(e) =>
                                        update(
                                            'samples',
                                            data.samples.map((v, j) =>
                                                j === i
                                                    ? {
                                                          ...v,
                                                          label: e.target.value,
                                                      }
                                                    : v,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <label className="market-field">
                                <span>{t('HTTPS address')}</span>
                                <Input
                                    type="url"
                                    value={sample.url}
                                    maxLength={2000}
                                    onChange={(e) =>
                                        update(
                                            'samples',
                                            data.samples.map((v, j) =>
                                                j === i
                                                    ? {
                                                          ...v,
                                                          url: e.target.value,
                                                      }
                                                    : v,
                                            ),
                                        )
                                    }
                                />
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    update(
                                        'samples',
                                        data.samples.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                {t('Remove')}
                            </Button>
                        </div>
                    ))}
                    {data.samples.length < 3 && (
                        <div className="market-actions">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    update('samples', [
                                        ...data.samples,
                                        { label: '', url: '' },
                                    ])
                                }
                            >
                                {t('Add work sample')}
                            </Button>
                            {sampleLinks
                                .filter(
                                    (link) =>
                                        !data.samples.some(
                                            (sample) => sample.url === link.url,
                                        ),
                                )
                                .map((link) => (
                                    <Button
                                        key={link.url}
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            update('samples', [
                                                ...data.samples,
                                                link,
                                            ])
                                        }
                                    >
                                        {t('Use :label', { label: link.label })}
                                    </Button>
                                ))}
                        </div>
                    )}
                    {!trial && (
                        <fieldset className="proposal-cases-field">
                            <legend>
                                <h2>{t('Case studies (optional)')}</h2>
                            </legend>
                            <p
                                className="market-muted"
                                id="proposal-cases-help"
                            >
                                {t(
                                    'Choose up to :count published case studies from your portfolio. The client receives them as they are when you submit, and keeps that copy.',
                                    { count: caseLimit },
                                )}
                            </p>
                            {caseOptions.length ? (
                                <ul className="proposal-case-options">
                                    {caseOptions.map((option) => {
                                        const chosen = data.cases.includes(
                                            option.id,
                                        );
                                        return (
                                            <li key={option.id}>
                                                <label>
                                                    <input
                                                        type="checkbox"
                                                        checked={chosen}
                                                        disabled={
                                                            !chosen &&
                                                            (!option.shareable ||
                                                                data.cases
                                                                    .length >=
                                                                    caseLimit)
                                                        }
                                                        aria-describedby="proposal-cases-help"
                                                        onChange={() =>
                                                            update(
                                                                'cases',
                                                                chosen
                                                                    ? data.cases.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              option.id,
                                                                      )
                                                                    : [
                                                                          ...data.cases,
                                                                          option.id,
                                                                      ],
                                                            )
                                                        }
                                                    />
                                                    <span>
                                                        <strong dir="auto">
                                                            {option.title}
                                                        </strong>
                                                        <span
                                                            dir="auto"
                                                            className="market-muted"
                                                        >
                                                            {option.summary}
                                                        </span>
                                                        {!option.shareable && (
                                                            <span className="market-error">
                                                                {t(
                                                                    'Not public, so it cannot be sent.',
                                                                )}
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <p>
                                    <Link href="/my-portfolio">
                                        {t(
                                            'Publish a case study in your portfolio to share it here.',
                                        )}
                                    </Link>
                                </p>
                            )}
                        </fieldset>
                    )}
                    <fieldset className="attached-files-block">
                        <legend>
                            <h2>{t('Attached files (optional)')}</h2>
                        </legend>
                        {trial ? (
                            <p>
                                {t(
                                    'Nobody receives a proposal on a sample project, so it takes no files.',
                                )}
                            </p>
                        ) : demo ? (
                            <DemoOffNote>
                                {t(
                                    'Files are switched off in the shared demo.',
                                )}
                            </DemoOffNote>
                        ) : (
                            <>
                                {/* Who will see the files is said before the control, not after an upload. */}
                                <p id="proposal-files-audience">
                                    {t(
                                        'Only this project’s client can download them, and only once you submit. A file you add or remove later reaches the client with your next submission.',
                                    )}
                                </p>
                                <AttachedFiles
                                    files={files}
                                    href={(file) =>
                                        `/proposals/${proposalId}/files/${file.id}`
                                    }
                                    remove={{
                                        disabled: fileBusy || conflict,
                                        label: (file) =>
                                            t('Remove :name', {
                                                name: file.name,
                                            }),
                                        onClick: detach,
                                    }}
                                />
                                <div className="attached-files-field">
                                    <input
                                        ref={picker}
                                        id="proposal-file"
                                        type="file"
                                        className="sr-only"
                                        accept={FILE_ACCEPT}
                                        disabled={
                                            fileBusy ||
                                            busy ||
                                            conflict ||
                                            files.length >= fileLimit
                                        }
                                        aria-describedby="proposal-files-audience proposal-files-rules"
                                        onChange={(e) =>
                                            void attach(e.target.files?.[0])
                                        }
                                    />
                                    <label htmlFor="proposal-file">
                                        {t('Choose a file')}
                                    </label>
                                    <span>
                                        {t(':count of :total files attached', {
                                            count: files.length,
                                            total: fileLimit,
                                        })}
                                    </span>
                                </div>
                                <small id="proposal-files-rules">
                                    {t(
                                        'Up to :count files, 5 MB each: JPG, PNG, WebP, PDF, TXT or ZIP.',
                                        { count: fileLimit },
                                    )}
                                </small>
                                <p
                                    role={fileNote?.error ? 'alert' : 'status'}
                                    className={
                                        fileNote?.error
                                            ? 'attached-files-note attached-files-error'
                                            : 'attached-files-note'
                                    }
                                >
                                    {fileNote?.text}
                                </p>
                            </>
                        )}
                    </fieldset>
                    <div className="market-actions">
                        <Button
                            type="submit"
                            disabled={busy || fileBusy || conflict || !eligible}
                        >
                            {t(
                                proposal?.submitted_at
                                    ? 'Submit updated proposal'
                                    : 'Submit proposal',
                            )}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy || conflict}
                            onClick={() => void save('save')}
                        >
                            {t('Save draft')}
                        </Button>
                        <span role="status">
                            {busy
                                ? t('Saving…')
                                : saved
                                  ? t('Draft saved')
                                  : t('Changes autosave')}
                        </span>
                    </div>
                    <Link href="/my-proposals">{t('My proposals')}</Link>
                </form>
                <aside>
                    <ProjectContext project={project} />
                </aside>
            </div>
        </ProposalLayout>
    );
}
