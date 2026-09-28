import { Link } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate, type Page } from '@/pages/discovery/shared';
import { MessageLayout, type ConversationSummary } from './shared';

export default function Index({
    conversations,
    archived,
}: {
    conversations: Page<ConversationSummary>;
    archived: boolean;
}) {
    const { t } = useTranslation();
    return (
        <MessageLayout>
            <h2>
                {archived ? t('Archived conversations') : t('Conversations')}
            </h2>
            {!conversations.data.length && (
                <section className="market-panel">
                    <p>{t('No conversations here yet.')}</p>
                    <p className="market-muted">
                        {t(
                            'Clients can start a conversation after receiving a proposal.',
                        )}
                    </p>
                </section>
            )}
            {conversations.data.map((conversation) => (
                <article
                    key={conversation.id}
                    className="market-panel market-stack"
                >
                    <div className="market-actions">
                        <span dir="auto">{conversation.counterpart}</span>
                        <JobDate value={conversation.updated_at} />
                        {conversation.unread > 0 && (
                            <span>
                                {t(':count unread', {
                                    count: conversation.unread,
                                })}
                            </span>
                        )}
                    </div>
                    {conversation.contract_id && (
                        <Link href={`/contracts/${conversation.contract_id}`}>
                            {t('Open contract')}
                        </Link>
                    )}
                    <h2>
                        <Link href={`/messages/${conversation.id}`} dir="auto">
                            {conversation.project.title}
                        </Link>
                    </h2>
                </article>
            ))}
            <Pagination data={conversations} />
        </MessageLayout>
    );
}
