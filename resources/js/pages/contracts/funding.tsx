import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import { Money } from '@/pages/discovery/shared';
import {
    PaymentStatus,
    ProviderOptions,
    usePaymentReason,
    useProviderLabel,
    type Contract,
    type Payment,
} from '@/pages/offers/shared';

export function Funding({
    contract,
    payment,
    providers,
    funding_paused,
}: {
    contract: Contract;
    payment: Payment | null;
    providers: string[];
    funding_paused: boolean;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const providerLabel = useProviderLabel();
    const paymentReason = usePaymentReason();
    // One token per page load: a double click or retry reuses the same attempt.
    const form = useForm({
        provider: providers[0] ?? '',
        client_token: crypto.randomUUID(),
    });
    const [busy, setBusy] = useState(false);
    const act = (path: string, confirmation?: string) => {
        if (busy || (confirmation && !window.confirm(confirmation))) return;
        setBusy(true);
        router.post(
            `/payments/${payment?.id}/${path}`,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    const fund = () => {
        // Continuing reuses the open attempt's provider.
        form.transform((data) => ({
            ...data,
            provider: pending && payment ? payment.provider : data.provider,
        }));
        form.post(`/contracts/${contract.id}/payments`, {
            preserveScroll: true,
        });
    };
    const awaiting = contract.status === 'awaiting_payment';
    const pending = payment?.status === 'pending';
    return (
        <section className="market-panel market-stack">
            <div className="market-actions">
                <h2 className="!mb-0">{t('Funding')}</h2>
                <span className="proposal-status">{t('Test mode')}</span>
            </div>
            <InputError message={errors.payment ?? errors.provider} />
            {!awaiting && payment && (
                <p>
                    {t('A test payment through :provider was verified.', {
                        provider: providerLabel(payment.provider),
                    })}{' '}
                    <Money
                        min={contract.agreement.amount}
                        max={contract.agreement.amount}
                    />
                </p>
            )}
            {!awaiting && (
                <p className="market-muted">
                    {t(
                        'Elancer does not hold or move real money, so completion releases no payout.',
                    )}
                </p>
            )}
            {awaiting && pending && payment && (
                <>
                    <p>
                        {t(
                            'A payment through :provider is being checked. The contract activates only after the provider confirms it.',
                            { provider: providerLabel(payment.provider) },
                        )}
                    </p>
                    <div className="market-actions">
                        <Button disabled={busy} onClick={() => act('check')}>
                            {t('Check payment status')}
                        </Button>
                        {contract.is_client && (
                            <>
                                <Button
                                    variant="outline"
                                    disabled={busy || form.processing}
                                    onClick={fund}
                                >
                                    {t('Continue payment')}
                                </Button>
                                <Button
                                    variant="danger-outline"
                                    disabled={busy}
                                    onClick={() =>
                                        act(
                                            'cancel',
                                            t(
                                                'Cancel this payment attempt? If the provider already completed it, the contract is funded instead.',
                                            ),
                                        )
                                    }
                                >
                                    {t('Cancel attempt')}
                                </Button>
                            </>
                        )}
                    </div>
                </>
            )}
            {awaiting && !pending && (
                <>
                    {payment && (
                        <p className="market-muted">
                            {t('Last attempt')}:{' '}
                            <PaymentStatus status={payment.status} />{' '}
                            {paymentReason(payment.failure_reason)}
                        </p>
                    )}
                    {funding_paused ? (
                        <p>
                            {t(
                                'Funding is paused while an account on this contract is restricted.',
                            )}
                        </p>
                    ) : !contract.is_client ? (
                        <p>
                            {t(
                                'Waiting for the client to fund this contract. The delivery clock has not started.',
                            )}
                        </p>
                    ) : providers.length === 0 ? (
                        <p>
                            {t(
                                'No payment provider is connected yet, so funding is not available.',
                            )}
                        </p>
                    ) : (
                        <>
                            <p>
                                {t(
                                    'Fund the agreed amount to start the work. This is a test payment: no real money moves.',
                                )}{' '}
                                <Money
                                    min={contract.agreement.amount}
                                    max={contract.agreement.amount}
                                />
                            </p>
                            <ProviderOptions
                                providers={providers}
                                value={form.data.provider}
                                onChange={(provider) =>
                                    form.setData('provider', provider)
                                }
                            />
                            <div>
                                <Button
                                    disabled={form.processing}
                                    onClick={fund}
                                >
                                    {t('Fund contract')}
                                </Button>
                            </div>
                        </>
                    )}
                </>
            )}
        </section>
    );
}
