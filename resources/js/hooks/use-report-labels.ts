import { useTranslation } from '@/hooks/use-translation';

export type ReportTarget =
    | 'project'
    | 'profile'
    | 'case'
    | 'message'
    | 'contract';

/** One place for the words every report screen shares, in the current language. */
export function useReportLabels() {
    const { t, locale } = useTranslation();
    const targets: Record<string, string> = {
        project: t('Project'),
        profile: t('Freelancer profile'),
        case: t('Case study'),
        message: t('Message'),
        contract: t('Contract'),
    };
    const reasons: Record<string, string> = {
        spam_scam: t('Spam or scam'),
        harassment: t('Harassment or abuse'),
        inappropriate: t('Inappropriate content'),
        off_platform: t('Asking to pay or talk outside Elancer'),
        fake_identity: t('Fake or misleading identity'),
        other: t('Other'),
    };
    const statuses: Record<string, string> = {
        submitted: t('Submitted'),
        in_review: t('In review'),
        resolved: t('Resolved'),
    };
    // Q66: the whole of what a reporter is told about the result.
    const outcomes: Record<string, string> = {
        action_taken: t('We reviewed your report and took action.'),
        no_violation: t(
            'We reviewed your report and found no breach of the Elancer rules.',
        ),
        not_confirmed: t(
            'We could not confirm the problem from the information available.',
        ),
        duplicate: t('This was already handled through another report.'),
    };
    const time = (value: string) =>
        new Date(value).toLocaleString(locale, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    return { targets, reasons, statuses, outcomes, time };
}
