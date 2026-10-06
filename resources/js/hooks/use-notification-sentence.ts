import { useTranslation } from '@/hooks/use-translation';

// Events are stored by kind so they read in the recipient's current language.
export function useNotificationSentence() {
    const { t } = useTranslation();
    const sentences: Record<string, string> = {
        invitation_received: t('invited you to apply to'),
        proposal_received: t('sent a proposal for'),
        offer_received: t('sent you a final offer for'),
        offer_accepted: t('accepted your offer for'),
        offer_declined: t('declined your offer for'),
        offer_changes_requested: t('requested changes to your offer for'),
        offer_withdrawn: t('withdrew the offer for'),
        contract_funded: t('funded the contract for'),
        payment_verified: t('Your test payment was verified for'),
        message_received: t('sent you a message about'),
        delivery_submitted: t('submitted a delivery for'),
        revision_requested: t('requested a revision for'),
        contract_completed: t('approved your delivery and completed'),
        review_received: t('left you a review for'),
        cancellation_requested: t('asked to cancel the contract for'),
        cancellation_accepted: t('accepted cancelling the contract for'),
        cancellation_declined: t('declined cancelling the contract for'),
        cancellation_withdrawn: t(
            'withdrew the request to cancel the contract for',
        ),
        contract_cancelled: t('cancelled the unfunded contract for'),
        contract_refunded: t(
            'The test payment was refunded and the contract cancelled for',
        ),
        amendment_proposed: t('proposed a change to the contract for'),
        amendment_accepted: t(
            'accepted your proposed change to the contract for',
        ),
        amendment_declined: t(
            'declined your proposed change to the contract for',
        ),
        amendment_withdrawn: t(
            'withdrew a proposed change to the contract for',
        ),
        portfolio_requested: t(
            'asked you to approve a public case study about',
        ),
        portfolio_approved: t('approved your case study about'),
        portfolio_declined: t('declined your case study about'),
        portfolio_revoked: t(
            'withdrew permission to publish your case study about',
        ),
    };
    return (kind: string | null) =>
        sentences[kind ?? ''] ?? t('Workspace update for');
}
