export type OverviewData = {
    client: boolean;
    counts: {
        projects: number;
        proposals: number;
        invitations: number;
        contracts: number;
        awaiting_payment: number;
        unread: number;
    };
    finance: Record<'awaiting' | 'funded', { count: number; total: string }>;
    chart: { week: string; proposals: number; contracts: number }[];
    work: {
        id: string;
        title: string | null;
        status: string;
        kind: 'project' | 'proposal';
        href: string;
        count: number | null;
        updated_at: string;
    }[];
    agreements: {
        id: string;
        title: string | null;
        status: string;
        kind: 'contract';
        href: string;
        count: null;
        updated_at: string;
    }[];
    activity: {
        id: string;
        kind: 'message' | 'proposal' | 'contract';
        actor: string | null;
        title: string | null;
        preview: string | null;
        href: string;
        created_at: string;
    }[];
    profile_checks: Partial<
        Record<'headline' | 'bio' | 'skills' | 'location', boolean>
    >;
};
