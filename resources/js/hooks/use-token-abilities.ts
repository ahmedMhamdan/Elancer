import { useTranslation } from '@/hooks/use-translation';

export type AbilityLabel = { label: string; hint: string };

// Plans 028 and 033: what each token ability allows, in the member's language. The API tokens page
// lists them on its form and the AI agents page names the one each tool needs.
export function useTokenAbilities(): Record<string, AbilityLabel> {
    const { t } = useTranslation();

    return {
        'projects:read': {
            label: t('Read projects'),
            hint: t('The public project list and each project page.'),
        },
        'proposals:read': {
            label: t('Read my proposals'),
            hint: t('Your own drafts and submitted proposals.'),
        },
        'proposals:write': {
            label: t('Write my proposals'),
            hint: t('Save drafts, submit, change and withdraw your proposals.'),
        },
        'hiring:read': {
            label: t('Read my projects and received proposals'),
            hint: t(
                'Your own projects as a client and the proposals they received, read only. Used by AI agents; the API routes below do not read it.',
            ),
        },
    };
}
