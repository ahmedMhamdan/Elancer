// Q25 workspace switch. Adapts local TailAdmin src/components/common/ChartTab.tsx (MIT):
// the same segmented pair of buttons, on Elancer tokens, with a pressed state and a server save.
import { router, usePage } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { useState } from 'react';
import { useSidebar } from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';

export default function WorkspaceSwitch() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const { open, isMobile, setOpenMobile } = useSidebar();
    const [busy, setBusy] = useState(false);
    const wide = open || isMobile;
    const current =
        auth.user.workspace_role === 'client' ? 'client' : 'freelancer';
    return (
        <div
            className="workspace-switch"
            role="group"
            aria-label={t('Workspace')}
            aria-busy={busy}
            title={!wide ? t('Workspace') : undefined}
            data-expanded={wide}
        >
            <span className="workspace-switch-icon" aria-hidden="true">
                <ArrowLeftRight size={20} />
            </span>
            <div className="workspace-switch-options">
                {(['client', 'freelancer'] as const).map((workspace) => (
                    <button
                        key={workspace}
                        type="button"
                        aria-pressed={current === workspace}
                        // Not `disabled`: a disabled button drops focus, which would fold the sidebar under a keyboard user.
                        aria-disabled={busy}
                        onClick={() => {
                            if (busy || current === workspace) return;
                            router.patch(
                                '/workspace',
                                { workspace },
                                {
                                    preserveScroll: true,
                                    onStart: () => setBusy(true),
                                    // The address does not change, so the phone menu would otherwise stay over the page.
                                    onSuccess: () => setOpenMobile(false),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {t(workspace === 'client' ? 'Client' : 'Freelancer')}
                    </button>
                ))}
            </div>
        </div>
    );
}
