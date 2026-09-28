import { Link, useForm } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import TextArea from '@/components/tailadmin/textarea';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import { AgreementLayout } from './shared';

export default function Create({
    proposal,
    project,
}: {
    proposal: {
        id: number;
        version: number;
        content: { price?: string; duration_days?: number } | null;
    };
    project: { id: number; title: string };
}) {
    const { t } = useTranslation();
    const form = useForm({
        scope: '',
        deliverables: '',
        amount: proposal.content?.price ?? '',
        duration_days: String(proposal.content?.duration_days ?? 7),
        revision_rounds: '2',
        proposal_version: proposal.version,
        client_token: crypto.randomUUID(),
        offer: '',
    });
    return (
        <AgreementLayout title={t('Send final offer')}>
            <h2 dir="auto">{project.title}</h2>
            <Link href={`/proposals/${proposal.id}`}>{t('View proposal')}</Link>
            <form
                className="market-panel market-stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (
                        window.confirm(
                            t(
                                'Send this final offer? Its terms cannot be edited and it expires in 72 hours.',
                            ),
                        )
                    ) {
                        form.transform((data) => ({
                            ...data,
                            deliverables: data.deliverables
                                .split('\n')
                                .map((line) => line.trim())
                                .filter(Boolean),
                        }));
                        form.post(`/proposals/${proposal.id}/offer`);
                    }
                }}
            >
                <p className="market-muted">
                    {t(
                        'Only one offer can be pending for this project. Other applicants can still apply until the cutoff.',
                    )}
                </p>
                <InputError message={form.errors.offer} />
                <div className="market-field">
                    <label htmlFor="offer-scope">{t('Scope summary')}</label>
                    <TextArea
                        id="offer-scope"
                        value={form.data.scope}
                        onChange={(value) => form.setData('scope', value)}
                        required
                        minLength={50}
                        maxLength={20000}
                        rows={6}
                    />
                    <InputError message={form.errors.scope} />
                </div>
                <div className="market-field">
                    <label htmlFor="offer-deliverables">
                        {t('Named deliverables')}
                    </label>
                    <TextArea
                        id="offer-deliverables"
                        value={form.data.deliverables}
                        onChange={(value) =>
                            form.setData('deliverables', value)
                        }
                        required
                        rows={5}
                    />
                    <p className="market-muted">
                        {t(
                            'One deliverable per line, up to 20. Each name can contain up to 200 characters.',
                        )}
                    </p>
                    {Object.entries(form.errors)
                        .filter(([key]) => key.startsWith('deliverables'))
                        .map(([key, error]) => (
                            <InputError key={key} message={error} />
                        ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="market-field">
                        <label htmlFor="offer-amount">
                            {t('Fixed price (USD)')}
                        </label>
                        <Input
                            id="offer-amount"
                            type="number"
                            min="1"
                            max="1000000"
                            step={0.01}
                            required
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                        <InputError message={form.errors.amount} />
                    </div>
                    <div className="market-field">
                        <label htmlFor="offer-duration">
                            {t('Delivery duration (calendar days)')}
                        </label>
                        <Input
                            id="offer-duration"
                            type="number"
                            min="1"
                            max="365"
                            required
                            value={form.data.duration_days}
                            onChange={(event) =>
                                form.setData(
                                    'duration_days',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.duration_days} />
                    </div>
                    <div className="market-field">
                        <label htmlFor="offer-revisions">
                            {t('Included revision rounds')}
                        </label>
                        <Input
                            id="offer-revisions"
                            type="number"
                            min="0"
                            max="20"
                            required
                            value={form.data.revision_rounds}
                            onChange={(event) =>
                                form.setData(
                                    'revision_rounds',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.revision_rounds} />
                    </div>
                </div>
                <p className="market-muted">
                    {t(
                        'Delivery time starts only after verified sandbox funding. Weekends count as calendar days.',
                    )}
                </p>
                <div className="market-actions">
                    <Button type="submit" disabled={form.processing}>
                        {t('Send final offer')}
                    </Button>
                    <Link href={`/proposals/${proposal.id}`}>
                        {t('Cancel')}
                    </Link>
                </div>
            </form>
        </AgreementLayout>
    );
}
