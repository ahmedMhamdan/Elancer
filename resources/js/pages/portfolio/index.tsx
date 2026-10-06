import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import {
    ApprovalHistory,
    CaseStatus,
    PortfolioLayout,
    type OwnedCase,
} from './shared';

export default function Index({
    cases,
    contracts,
    profilePublic,
    limit,
}: {
    cases: OwnedCase[];
    contracts: { id: number; title: string }[];
    profilePublic: boolean;
    limit: number;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const [busy, setBusy] = useState(false);
    const act = (item: OwnedCase, action: string) => {
        if (busy) return;
        setBusy(true);
        router.patch(
            `/my-portfolio/${item.id}`,
            { action },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    const full = cases.length >= limit;
    return (
        <PortfolioLayout title={t('Portfolio')}>
            <Head title={t('Portfolio')} />
            <div className="market-stack">
                <p className="market-muted">
                    {t(
                        'Case studies show clients how you work. A saved case study is private until you publish it.',
                    )}
                </p>
                {!profilePublic && (
                    <p className="market-panel">
                        {t(
                            'Your freelancer profile is private, so none of your case studies are public right now.',
                        )}{' '}
                        <Link href="/my-profile">{t('My profile')}</Link>
                    </p>
                )}
                {errors.case && (
                    <p role="alert" className="market-error">
                        {errors.case}
                    </p>
                )}
                <div className="market-actions">
                    {full ? (
                        <p className="market-muted">
                            {t(
                                'A portfolio can hold at most :count case studies.',
                                { count: limit },
                            )}
                        </p>
                    ) : (
                        <Button
                            startIcon={<Plus size={16} />}
                            onClick={() => router.visit('/my-portfolio/create')}
                        >
                            {t('Add a case study')}
                        </Button>
                    )}
                </div>
                {!full && contracts.length > 0 && (
                    <section className="market-panel market-stack">
                        <h2 className="!mb-0">
                            {t('Completed work on Elancer')}
                        </h2>
                        <p className="market-muted">
                            {t(
                                'You can write a case study about a completed contract. It becomes public only when that client approves the exact text, images and links.',
                            )}
                        </p>
                        <ul className="market-stack">
                            {contracts.map((contract) => (
                                <li
                                    key={contract.id}
                                    className="market-actions"
                                >
                                    <span dir="auto">{contract.title}</span>
                                    <Link
                                        href={`/my-portfolio/create?contract=${contract.id}`}
                                    >
                                        {t('Write a case study')}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                {!cases.length && (
                    <p className="market-panel">{t('No case studies yet.')}</p>
                )}
                {cases.map((item) => (
                    <article
                        key={item.id}
                        className="market-panel market-stack"
                    >
                        <div className="market-actions">
                            <CaseStatus item={item} />
                        </div>
                        <h2 className="!mb-0" dir="auto">
                            {item.content.title}
                        </h2>
                        <p dir="auto" className="break-words">
                            {item.content.summary}
                        </p>
                        {item.contract && (
                            <p className="market-muted">
                                {t('Completed on Elancer:')}{' '}
                                <Link
                                    href={`/contracts/${item.contract.id}`}
                                    dir="auto"
                                >
                                    {item.contract.title}
                                </Link>
                            </p>
                        )}
                        {item.revoked_at && !item.public_content && (
                            <p className="market-muted">
                                {t(
                                    'The client withdrew permission, so this case study is not public. You can send it for approval again.',
                                )}
                            </p>
                        )}
                        <div className="market-actions">
                            <Link href={`/my-portfolio/${item.id}/edit`}>
                                {t('Edit')}
                            </Link>
                            {item.public_content &&
                                !item.hidden_at &&
                                profilePublic && (
                                    <Link href={`/portfolio/${item.id}`}>
                                        {t('View public page')}
                                    </Link>
                                )}
                            {!item.contract &&
                                (!item.public_content || item.changed) && (
                                    <Button
                                        disabled={busy}
                                        onClick={() => act(item, 'publish')}
                                    >
                                        {item.public_content
                                            ? t('Publish changes')
                                            : t('Publish')}
                                    </Button>
                                )}
                            {item.contract &&
                                !item.pending &&
                                (!item.public_content || item.changed) && (
                                    <Button
                                        disabled={busy}
                                        onClick={() => act(item, 'request')}
                                    >
                                        {t('Send for client approval')}
                                    </Button>
                                )}
                            {item.pending && (
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => act(item, 'withdraw')}
                                >
                                    {t('Withdraw request')}
                                </Button>
                            )}
                            {item.public_content && (
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() =>
                                        act(
                                            item,
                                            item.hidden_at ? 'show' : 'hide',
                                        )
                                    }
                                >
                                    {item.hidden_at ? t('Show') : t('Hide')}
                                </Button>
                            )}
                            {item.deletable && (
                                <Button
                                    variant="danger-outline"
                                    disabled={busy}
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                t(
                                                    'Delete this case study? This cannot be undone.',
                                                ),
                                            )
                                        )
                                            router.delete(
                                                `/my-portfolio/${item.id}`,
                                                { preserveScroll: true },
                                            );
                                    }}
                                >
                                    {t('Delete')}
                                </Button>
                            )}
                        </div>
                        <ApprovalHistory history={item.history} />
                    </article>
                ))}
            </div>
        </PortfolioLayout>
    );
}
