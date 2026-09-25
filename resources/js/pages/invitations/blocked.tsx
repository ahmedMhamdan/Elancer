import { router } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import { InvitationLayout } from './shared';

export default function Blocked({
    blocks,
}: {
    blocks: Page<{ id: number; name: string }>;
}) {
    const { t } = useTranslation();
    return (
        <InvitationLayout>
            <h2>{t('Blocked accounts')}</h2>
            <p>{t('Unblocking does not restore cancelled invitations.')}</p>
            {!blocks.data.length && (
                <p className="market-panel">{t('No blocked accounts.')}</p>
            )}
            {blocks.data.map((block) => (
                <article className="market-panel market-actions" key={block.id}>
                    <span dir="auto">{block.name}</span>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.delete(`/blocked-accounts/${block.id}`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        {t('Unblock')}
                    </Button>
                </article>
            ))}
            <Pagination data={blocks} />
        </InvitationLayout>
    );
}
