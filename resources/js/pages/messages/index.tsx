import {
    ChatTemplate,
    type ConversationFilters,
} from '@/components/ui/chat-template';
import type { Page } from '@/pages/discovery/shared';
import { MessageLayout, type ConversationSummary } from './shared';

export default function Index({
    conversations,
    filters,
}: {
    conversations: Page<ConversationSummary>;
    filters: ConversationFilters;
}) {
    return (
        <MessageLayout>
            <ChatTemplate conversations={conversations} filters={filters} />
        </MessageLayout>
    );
}
