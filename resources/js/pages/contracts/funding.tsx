import { router, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
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

// Plan 033: the providers' published test cards (docs.stripe.com/testing and
// docs.moyasar.com/guides/card-payments/test-cards). The keys are test keys only,
// so a real card is refused by the provider; this keeps one from being typed at all.
const TEST_CARDS: Record<string, { number: string; docs: string }> = {
    stripe: {
        number: '4242424242424242',
        docs: 'https://docs.stripe.com/testing#cards',
    },
    moyasar: {
        number: '4111111111111111',
        docs: 'https://docs.moyasar.com/guides/card-payments/test-cards',
    },
};

/** Adapted, like the suspension banner, from the local TailAdmin Alert warning variant (MIT). */
function TestCardNotice({ provider }: { provider: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const card = TEST_CARDS[provider];
    if (!card) return null;
    const rules =
        provider === 'stripe'
            ? t(
                  'Any future date such as 12/34 and any three-digit CVC. Stripe accepts nothing else here.',
              )
            : t(
                  'Any future month and year, any three-digit CVC and a name of two words. For Mada use 4201 3201 1111 1010. Moyasar accepts nothing else here.',
              );
    return (
        <div
            role="note"
            data-test="test-card"
            className="rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200"
        >
            <div className="flex items-start gap-3">
                <TriangleAlert
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="min-w-0 space-y-2 text-sm">
                    <h3 className="font-semibold">
                        {t('Use a test card, never your real one')}
                    </h3>
                    <p>
                        {t(
                            'Payments here are in test mode, so nothing is charged. Type only the test card below on the payment page.',
                        )}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <code
                            dir="ltr"
                            className="rounded-md border border-amber-500/40 bg-white/60 px-2 py-1 font-mono text-base tracking-wider select-all dark:bg-black/20"
                        >
                            {card.number.replace(/(\d{4})(?=\d)/g, '$1 ')}
                        </code>
                        <button
                            type="button"
                            className="focus-visible:outline-ring inline-flex min-h-11 items-center px-1 font-medium underline underline-offset-4 focus-visible:outline-2"
                            onClick={() => {
                                void navigator.clipboard
                                    .writeText(card.number)
                                    .then(() => setCopied(true));
                            }}
                        >
                            {copied ? t('Copied') : t('Copy card number')}
                        </button>
                    </div>
                    <p>{rules}</p>
                    <a
                        href={card.docs}
                        target="_blank"
                        rel="noreferrer"
                        className="focus-visible:outline-ring inline-flex min-h-11 items-center font-medium underline underline-offset-4 focus-visible:outline-2"
                    >
                        {t('More test cards')}
                    </a>
                </div>
            </div>
        </div>
    );
}

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
                    {contract.is_client && (
                        <TestCardNotice provider={payment.provider} />
                    )}
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
                            <TestCardNotice provider={form.data.provider} />
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
