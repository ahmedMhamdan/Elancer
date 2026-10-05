import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import DatePicker from '@/components/tailadmin/date-picker';
import Input from '@/components/tailadmin/input';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { OfferTime, type Contract } from '@/pages/offers/shared';

export type Amendment = {
    id: number;
    status: string;
    reason: string;
    date_kind: string | null;
    new_due_at: string | null;
    previous_due_at: string | null;
    extra_rounds: number;
    created_at: string;
    decided_at: string | null;
    mine: boolean;
};

const WORKING = ['active', 'submitted', 'revision_requested'];

// What a proposal changes, beside what applied when it was made.
function AmendmentChanges({ amendment }: { amendment: Amendment }) {
    const { t, locale } = useTranslation();
    return (
        <dl className="proposal-terms">
            {amendment.new_due_at && (
                <>
                    <div>
                        <dt>
                            {amendment.date_kind === 'revision'
                                ? t('Proposed revision date')
                                : t('Proposed first delivery date')}
                        </dt>
                        <dd>
                            <OfferTime value={amendment.new_due_at} />
                        </dd>
                    </div>
                    <div>
                        <dt>{t('Date when proposed')}</dt>
                        <dd>
                            {amendment.previous_due_at ? (
                                <OfferTime value={amendment.previous_due_at} />
                            ) : (
                                t('No date agreed')
                            )}
                        </dd>
                    </div>
                </>
            )}
            {amendment.extra_rounds > 0 && (
                <div>
                    <dt>{t('Extra revision rounds')}</dt>
                    <dd>{amendment.extra_rounds.toLocaleString(locale)}</dd>
                </div>
            )}
        </dl>
    );
}

// Shown above the tabs while a proposed change awaits its answer.
export function AmendmentStatus({
    contract,
    amendment,
}: {
    contract: Contract;
    amendment: Amendment;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const [busy, setBusy] = useState(false);
    const counterpart = contract.is_client
        ? contract.agreement.freelancer_name
        : contract.agreement.client_name;
    const respond = (action: string) => {
        if (busy) return;
        setBusy(true);
        router.patch(
            `/contracts/${contract.id}/amendments`,
            { amendment: amendment.id, action },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    return (
        <section className="market-panel market-stack">
            <div className="market-actions">
                <h2 className="!mb-0">{t('Proposed change')}</h2>
                <span className="market-muted">
                    <OfferTime value={amendment.created_at} />
                </span>
            </div>
            <p>
                {amendment.mine
                    ? t('You proposed a change to this contract.')
                    : t(':name proposed a change to this contract.', {
                          name: counterpart,
                      })}
            </p>
            <AmendmentChanges amendment={amendment} />
            <p dir="auto" className="market-prose break-words">
                {amendment.reason}
            </p>
            <p className="market-muted">
                {t(
                    'Nothing changes unless the other participant accepts. The contract price stays the same.',
                )}
            </p>
            <InputError message={errors.amendment} />
            {WORKING.includes(contract.status) && (
                <div className="market-actions">
                    {amendment.mine ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => respond('withdraw')}
                        >
                            {t('Withdraw proposed change')}
                        </Button>
                    ) : (
                        <>
                            <Button
                                disabled={busy}
                                onClick={() => respond('accept')}
                            >
                                {t('Accept change')}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => respond('decline')}
                            >
                                {t('Decline change')}
                            </Button>
                        </>
                    )}
                </div>
            )}
        </section>
    );
}

export function AmendmentRequest({ contract }: { contract: Contract }) {
    const { t } = useTranslation();
    const form = useForm({ reason: '', due_on: '', extra_rounds: '' });
    const errors = form.errors as Record<string, string>;
    // A date belongs to the step being worked on; a delivery under review has none to move.
    const dated = contract.status !== 'submitted';
    return (
        <details className="market-panel contract-cancel">
            <summary>
                {t('Propose a new date or extra revision rounds')}
            </summary>
            <form
                className="market-stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform(({ reason, due_on, extra_rounds }) => ({
                        reason,
                        // End of the chosen day where the proposer is, sent as an exact moment.
                        due_at: due_on
                            ? new Date(`${due_on}T23:59:59`).toISOString()
                            : null,
                        extra_rounds: extra_rounds || null,
                    }));
                    form.post(`/contracts/${contract.id}/amendments`, {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <p className="market-muted">
                    {t(
                        'A change applies only if the other participant accepts it. The contract price stays the same, and one proposal can be open at a time.',
                    )}
                </p>
                {dated && (
                    <div className="market-field">
                        <label htmlFor="amendment-date">
                            {contract.status === 'revision_requested'
                                ? t('New revision date')
                                : t('New first delivery date')}
                        </label>
                        <DatePicker
                            id="amendment-date"
                            min="today"
                            placeholder={t('Choose a date')}
                            value={form.data.due_on}
                            onChange={(value) => form.setData('due_on', value)}
                        />
                        <InputError message={errors.due_at} />
                    </div>
                )}
                <div className="market-field">
                    <label htmlFor="amendment-rounds">
                        {t('Extra revision rounds')}
                    </label>
                    <Input
                        id="amendment-rounds"
                        type="number"
                        inputMode="numeric"
                        min="1"
                        max="5"
                        value={form.data.extra_rounds}
                        onChange={(event) =>
                            form.setData('extra_rounds', event.target.value)
                        }
                    />
                    <p className="market-muted">
                        {t('Leave empty to keep the current allowance.')}
                    </p>
                    <InputError message={errors.extra_rounds} />
                </div>
                <div className="market-field">
                    <label htmlFor="amendment-reason">
                        {t('Reason for the change')}
                    </label>
                    <TextArea
                        id="amendment-reason"
                        placeholder={t('Explain why this change is needed.')}
                        value={form.data.reason}
                        onChange={(value) => form.setData('reason', value)}
                        required
                        minLength={10}
                        maxLength={3000}
                        rows={4}
                    />
                    <InputError message={errors.reason ?? errors.amendment} />
                </div>
                <div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        {t('Send proposal')}
                    </Button>
                </div>
            </form>
        </details>
    );
}

// Every proposal and its outcome; the accepted agreement above is never rewritten.
export function AmendmentHistory({ amendments }: { amendments: Amendment[] }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Pending'),
        accepted: t('Accepted'),
        declined: t('Declined'),
        withdrawn: t('Withdrawn'),
        closed: t('Closed unanswered'),
    };
    if (!amendments.length) return null;
    return (
        <section className="market-panel market-stack">
            <h2>{t('Changes after acceptance')}</h2>
            {amendments.map((amendment) => (
                <div key={amendment.id} className="delivery-revision">
                    <div className="market-actions">
                        <span
                            className={
                                amendment.status === 'accepted'
                                    ? 'proposal-status proposal-status-submitted'
                                    : 'proposal-status'
                            }
                        >
                            {labels[amendment.status] ?? amendment.status}
                        </span>
                        <span className="market-muted">
                            <OfferTime
                                value={
                                    amendment.decided_at ?? amendment.created_at
                                }
                            />
                        </span>
                    </div>
                    <AmendmentChanges amendment={amendment} />
                    <p dir="auto" className="market-prose break-words">
                        {amendment.reason}
                    </p>
                </div>
            ))}
        </section>
    );
}
