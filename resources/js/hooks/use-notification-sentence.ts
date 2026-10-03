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
    };
    return (kind: string | null) =>
        sentences[kind ?? ''] ?? t('Workspace update for');
}
