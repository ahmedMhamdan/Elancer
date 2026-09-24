import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { WorkLink } from '@/components/freelancer-card';
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
};
export default function Edit({
    project,
    proposal,
    profilePublished,
    sampleLinks,
}: {
    project: ProposalProject;
    proposal: Proposal | null;
    profilePublished: boolean;
    sampleLinks: WorkLink[];
}) {
    const { t } = useTranslation();
    const initial = proposal?.draft ?? proposal?.content;
    const [data, setData] = useState<Draft>({
        price: initial?.price ?? '',
        duration_days: String(initial?.duration_days ?? ''),
        message: initial?.message ?? '',
        answers: (project.screening_questions ?? []).map(
            (_, i) => initial?.answers[i] ?? '',
        ),
        samples: initial?.samples ?? [],
    });
    const [busy, setBusy] = useState(false);
    const [saved, setSaved] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const [conflict, setConflict] = useState(false);
    const version = useRef(proposal?.version ?? 0);
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
                    <p className="market-muted">
                        {t(
                            'Drafts are private. Only submitting shares your proposal with the client.',
                        )}
                    </p>
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
                    <div className="market-actions">
                        <Button
                            type="submit"
                            disabled={busy || conflict || !eligible}
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
