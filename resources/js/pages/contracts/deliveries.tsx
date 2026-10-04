import { router, useForm, usePage } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { OfferTime, type Contract } from '@/pages/offers/shared';

export type Submission = {
    id: number;
    number: number;
    message: string;
    links: string[];
    created_at: string;
    files: { id: number; name: string; size: number }[];
    revision: { round: number; changes: string; created_at: string } | null;
};

function DeliveryForm({ contract }: { contract: Contract }) {
    const { t } = useTranslation();
    // One token per page load: a double click or retry records one delivery.
    const form = useForm({
        message: '',
        links: '',
        files: [] as File[],
        complete: false,
        client_token: crypto.randomUUID(),
    });
    const errors = form.errors as Record<string, string>;
    const listed = (prefix: string) =>
        Object.entries(errors)
            .filter(([key]) => key.startsWith(prefix))
            .map(([key, error]) => <InputError key={key} message={error} />);
    return (
        <form
            className="market-stack"
            onSubmit={(event) => {
                event.preventDefault();
                if (
                    !window.confirm(
                        t(
                            'Submit this formal delivery? It cannot be edited afterwards.',
                        ),
                    )
                )
                    return;
                form.transform((data) => ({
                    ...data,
                    links: data.links
                        .split('\n')
                        .map((line) => line.trim())
                        .filter(Boolean),
                }));
                form.post(`/contracts/${contract.id}/deliveries`, {
                    preserveScroll: true,
                    forceFormData: true,
                });
            }}
        >
            <h3>
                {contract.status === 'revision_requested'
                    ? t('Submit the revised delivery')
                    : t('Submit a delivery')}
            </h3>
            <p className="market-muted">
                {t(
                    'A formal delivery contains every named deliverable. Share partial progress in Messages instead.',
                )}
            </p>
            <InputError message={errors.delivery} />
            <div className="market-field">
                <label htmlFor="delivery-message">
                    {t('Delivery message')}
                </label>
                <TextArea
                    id="delivery-message"
                    placeholder={t(
                        'Describe what you are delivering and how to check it.',
                    )}
                    value={form.data.message}
                    onChange={(value) => form.setData('message', value)}
                    required
                    minLength={20}
                    maxLength={10000}
                    rows={5}
                />
                <InputError message={errors.message} />
            </div>
            <div className="market-field">
                <label htmlFor="delivery-links">{t('Links')}</label>
                <TextArea
                    id="delivery-links"
                    placeholder="https://"
                    dir="ltr"
                    value={form.data.links}
                    onChange={(value) => form.setData('links', value)}
                    rows={3}
                />
                <p className="market-muted">
                    {t('One link per line, up to 5.')}
                </p>
                {listed('links')}
            </div>
            <div className="market-field">
                <label htmlFor="delivery-files">{t('Files')}</label>
                <input
                    id="delivery-files"
                    type="file"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.zip"
                    className="delivery-file-input"
                    onChange={(event) =>
                        form.setData(
                            'files',
                            Array.from(event.target.files ?? []),
                        )
                    }
                />
                <p className="market-muted">
                    {t(
                        'Up to 3 files, 5 MB each: JPG, PNG, WebP, PDF, TXT or ZIP. Only you and the client can download them.',
                    )}
                </p>
                {listed('files')}
            </div>
            <label className="delivery-confirm">
                <input
                    type="checkbox"
                    checked={form.data.complete}
                    onChange={(event) =>
                        form.setData('complete', event.target.checked)
                    }
                    required
                />
                <span>
                    {t('This delivery includes every named deliverable.')}
                </span>
            </label>
            <InputError message={errors.complete} />
            <div>
                <Button type="submit" disabled={form.processing}>
                    {t('Submit delivery')}
                </Button>
            </div>
        </form>
    );
}

function Decision({
    contract,
    submission,
}: {
    contract: Contract;
    submission: Submission;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const form = useForm({ submission: submission.id, changes: '' });
    const [busy, setBusy] = useState(false);
    const remaining =
        contract.agreement.revision_rounds - contract.revisions_used;
    return (
        <div className="market-stack">
            <h3>{t('Your decision')}</h3>
            <p className="market-muted">
                {t(
                    'Check the delivery against the named deliverables. Nothing is approved automatically.',
                )}
            </p>
            <InputError message={errors.delivery} />
            <div>
                <Button
                    disabled={busy || form.processing}
                    onClick={() => {
                        if (
                            !window.confirm(
                                t(
                                    'Approve this delivery and complete the contract? This cannot be undone.',
                                ),
                            )
                        )
                            return;
                        setBusy(true);
                        router.post(
                            `/contracts/${contract.id}/complete`,
                            { submission: submission.id },
                            {
                                preserveScroll: true,
                                onFinish: () => setBusy(false),
                            },
                        );
                    }}
                >
                    {t('Approve and complete')}
                </Button>
            </div>
            {remaining > 0 ? (
                <form
                    className="market-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (
                            window.confirm(
                                t(
                                    'Send this revision request? It uses one included revision round.',
                                ),
                            )
                        )
                            form.post(`/contracts/${contract.id}/revisions`, {
                                preserveScroll: true,
                            });
                    }}
                >
                    <div className="market-field">
                        <label htmlFor="revision-changes">
                            {t('Requested changes')}
                        </label>
                        <TextArea
                            id="revision-changes"
                            placeholder={t(
                                'List every change you need in this one request.',
                            )}
                            value={form.data.changes}
                            onChange={(value) => form.setData('changes', value)}
                            required
                            minLength={20}
                            maxLength={10000}
                            rows={5}
                        />
                        <p className="market-muted">
                            {t(
                                'Group all changes into one request: each request uses one round, however many changes it lists.',
                            )}
                        </p>
                        <InputError
                            message={form.errors.changes ?? errors.revision}
                        />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={busy || form.processing}
                        >
                            {t('Request revision')}
                        </Button>
                    </div>
                </form>
            ) : (
                <p>
                    {t(
                        'Every included revision round has been used. You can approve this delivery or keep discussing it in Messages.',
                    )}
                </p>
            )}
        </div>
    );
}

export function Deliveries({
    contract,
    submissions,
}: {
    contract: Contract;
    submissions: Submission[];
}) {
    const { t, locale } = useTranslation();
    const size = (bytes: number) =>
        new Intl.NumberFormat(locale, {
            style: 'unit',
            unit:
                bytes < 1024
                    ? 'byte'
                    : bytes < 1048576
                      ? 'kilobyte'
                      : 'megabyte',
            maximumFractionDigits: 1,
        }).format(
            bytes < 1024
                ? bytes
                : bytes < 1048576
                  ? bytes / 1024
                  : bytes / 1048576,
        );
    const canDeliver =
        !contract.is_client &&
        ['active', 'revision_requested'].includes(contract.status);
    const canDecide = contract.is_client && contract.status === 'submitted';
    return (
        <>
            <section className="market-panel market-stack">
                <h2>{t('Named deliverables')}</h2>
                <ol className="list-decimal space-y-2 ps-6">
                    {contract.agreement.deliverables.map((item, index) => (
                        <li key={index} dir="auto" className="break-words">
                            {item}
                        </li>
                    ))}
                </ol>
                <p className="market-muted">
                    {t('Revision rounds used: :used of :total', {
                        used: contract.revisions_used.toLocaleString(locale),
                        total: contract.agreement.revision_rounds.toLocaleString(
                            locale,
                        ),
                    })}
                </p>
                {contract.status === 'awaiting_payment' && (
                    <p>{t('Deliveries open once funding is verified.')}</p>
                )}
                {canDeliver && <DeliveryForm contract={contract} />}
                {canDecide && submissions[0] && (
                    <Decision contract={contract} submission={submissions[0]} />
                )}
            </section>
            {submissions.map((submission) => (
                <article
                    key={submission.id}
                    className="market-panel market-stack"
                >
                    <div className="market-actions">
                        <h2 className="!mb-0">
                            {t('Delivery :number', {
                                number: submission.number.toLocaleString(
                                    locale,
                                ),
                            })}
                        </h2>
                        <span className="market-muted">
                            <OfferTime value={submission.created_at} />
                        </span>
                    </div>
                    <p dir="auto" className="market-prose break-words">
                        {submission.message}
                    </p>
                    {submission.links.length > 0 && (
                        <ul className="talent-links">
                            {submission.links.map((link) => (
                                <li key={link}>
                                    <a
                                        href={link}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <bdi className="break-all">{link}</bdi>
                                        <ExternalLink
                                            size={16}
                                            aria-label={t('Opens in a new tab')}
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                    {submission.files.length > 0 && (
                        <ul className="talent-links">
                            {submission.files.map((file) => (
                                <li key={file.id}>
                                    <a
                                        href={`/contracts/${contract.id}/files/${file.id}`}
                                        download
                                    >
                                        <bdi className="break-all">
                                            {file.name}
                                        </bdi>
                                        <span className="market-muted">
                                            {size(file.size)}
                                        </span>
                                        <Download
                                            size={16}
                                            aria-label={t('Download')}
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                    {submission.revision && (
                        <div className="delivery-revision">
                            <div className="market-actions">
                                <h3 className="!mb-0">
                                    {t('Revision request :number', {
                                        number: submission.revision.round.toLocaleString(
                                            locale,
                                        ),
                                    })}
                                </h3>
                                <span className="market-muted">
                                    <OfferTime
                                        value={submission.revision.created_at}
                                    />
                                </span>
                            </div>
                            <p dir="auto" className="market-prose break-words">
                                {submission.revision.changes}
                            </p>
                        </div>
                    )}
                </article>
            ))}
            {!submissions.length && contract.status !== 'awaiting_payment' && (
                <section className="market-panel">
                    <p>{t('No delivery has been submitted yet.')}</p>
                </section>
            )}
        </>
    );
}
