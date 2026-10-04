import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { OfferTime, type Contract } from '@/pages/offers/shared';

export type Cancellation = {
    status: string;
    reason: string;
    prior_status: string;
    refund_status: string | null;
    refund_failure: string | null;
    created_at: string;
    mine: boolean;
};

// Shown above the tabs while a request is unresolved or after the contract was cancelled.
export function CancellationStatus({
    contract,
    cancellation,
}: {
    contract: Contract;
    cancellation: Cancellation;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const failures: Record<string, string> = {
        declined: t('The provider declined the refund.'),
        provider_unavailable: t('The provider could not be reached.'),
        mismatch: t(
            'The provider reported a refund that does not match this contract.',
        ),
        unknown_payment: t('The provider has no record of this payment.'),
    };
    const [busy, setBusy] = useState(false);
    const counterpart = contract.is_client
        ? contract.agreement.freelancer_name
        : contract.agreement.client_name;
    const send = (
        method: 'patch' | 'post',
        path: string,
        data: Record<string, string>,
        confirmation?: string,
    ) => {
        if (busy || (confirmation && !window.confirm(confirmation))) return;
        setBusy(true);
        router[method](`/contracts/${contract.id}/cancellation${path}`, data, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };
    const respond = (action: string, confirmation?: string) =>
        send('patch', '', { action }, confirmation);
    return (
        <section className="market-panel market-stack">
            <div className="market-actions">
                <h2 className="!mb-0">{t('Cancellation')}</h2>
                <span className="market-muted">
                    <OfferTime value={cancellation.created_at} />
                </span>
            </div>
            <p>
                {cancellation.mine
                    ? t('You asked to cancel this contract.')
                    : t(':name asked to cancel this contract.', {
                          name: counterpart,
                      })}
            </p>
            <p dir="auto" className="market-prose break-words">
                {cancellation.reason}
            </p>
            <InputError message={errors.cancellation} />
            {cancellation.status === 'pending' && (
                <>
                    <p className="market-muted">
                        {t(
                            'Deliveries, revisions and approval are paused until this request is answered. Messages stay open.',
                        )}
                    </p>
                    <div className="market-actions">
                        {cancellation.mine ? (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => respond('withdraw')}
                            >
                                {t('Withdraw request')}
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="danger"
                                    disabled={busy}
                                    onClick={() =>
                                        respond(
                                            'accept',
                                            t(
                                                'Accept the cancellation? The test payment is refunded and the contract ends.',
                                            ),
                                        )
                                    }
                                >
                                    {t('Accept and refund')}
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => respond('decline')}
                                >
                                    {t('Decline and resume work')}
                                </Button>
                            </>
                        )}
                    </div>
                </>
            )}
            {cancellation.status === 'accepted' && (
                <>
                    <p>
                        {cancellation.refund_status === 'failed'
                            ? t(
                                  'The cancellation was accepted, but the test refund did not go through. The contract stays paused until it does.',
                              )
                            : t(
                                  'The cancellation was accepted. The provider is still processing the test refund; the contract ends when it confirms.',
                              )}{' '}
                        {failures[cancellation.refund_failure ?? '']}
                    </p>
                    <div>
                        <Button
                            disabled={busy}
                            onClick={() => send('post', '/refund', {})}
                        >
                            {cancellation.refund_status === 'failed'
                                ? t('Retry refund')
                                : t('Check refund status')}
                        </Button>
                    </div>
                </>
            )}
            {cancellation.status === 'refunded' && (
                <p>
                    {t(
                        'The test payment was refunded and the contract is cancelled.',
                    )}
                </p>
            )}
            {cancellation.status === 'cancelled' && (
                <p>{t('The contract was cancelled before any funding.')}</p>
            )}
        </section>
    );
}

export function CancellationRequest({ contract }: { contract: Contract }) {
    const { t } = useTranslation();
    const form = useForm({ reason: '' });
    const errors = form.errors as Record<string, string>;
    const funded = contract.status !== 'awaiting_payment';
    return (
        <details className="market-panel contract-cancel">
            <summary>{t('Cancel this contract')}</summary>
            <form
                className="market-stack"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (
                        window.confirm(
                            funded
                                ? t(
                                      'Send this cancellation request? Formal work pauses until it is answered.',
                                  )
                                : t(
                                      'Cancel this contract now? This cannot be undone.',
                                  ),
                        )
                    )
                        form.post(`/contracts/${contract.id}/cancellation`, {
                            preserveScroll: true,
                        });
                }}
            >
                <p className="market-muted">
                    {funded
                        ? t(
                              'A funded contract is cancelled only if the other participant agrees. The test payment is then refunded in full.',
                          )
                        : t(
                              'This contract is not funded yet, so either participant can cancel it at once.',
                          )}
                </p>
                <div className="market-field">
                    <label htmlFor="cancellation-reason">
                        {t('Reason for cancelling')}
                    </label>
                    <TextArea
                        id="cancellation-reason"
                        placeholder={t('Explain why the contract should end.')}
                        value={form.data.reason}
                        onChange={(value) => form.setData('reason', value)}
                        required
                        minLength={10}
                        maxLength={3000}
                        rows={4}
                    />
                    <InputError
                        message={errors.reason ?? errors.cancellation}
                    />
                </div>
                <div>
                    <Button
                        type="submit"
                        variant="danger-outline"
                        disabled={form.processing}
                    >
                        {funded
                            ? t('Request cancellation')
                            : t('Cancel contract')}
                    </Button>
                </div>
            </form>
        </details>
    );
}
