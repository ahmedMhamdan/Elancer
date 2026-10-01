import { Head } from '@inertiajs/react';
import OnboardingReady from '@/components/onboarding-ready';
import App1 from '@/components/ui/app-1';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { OverviewData } from '@/types/overview';
import type { MarketplaceProfile } from './marketplace-profile';
export type { MarketplaceProfile } from './marketplace-profile';

export default function Dashboard({
    profile,
    overview,
    onboardingReady = false,
}: {
    profile: MarketplaceProfile | null;
    overview: OverviewData;
    onboardingReady?: boolean;
}) {
    const { t } = useTranslation();
    return (
        <div className="workspace-dashboard min-w-0">
            <Head title={t('Overview')} />
            {onboardingReady && <OnboardingReady />}
            <App1 overview={overview} published={!!profile?.published_at} />
        </div>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Overview', href: dashboard() }] };
