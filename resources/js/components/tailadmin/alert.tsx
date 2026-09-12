// Adapted from the local TailAdmin src/components/ui/alert/Alert.tsx (MIT).
// Retains bordered variant container, icon/content flex structure; compact status
// message, accessible live region and Lucide icons replace demo title/link/SVGs.
import { CircleCheck, CircleAlert } from 'lucide-react';

export default function Alert({
    message,
    variant = 'success',
}: {
    message: string;
    variant?: 'success' | 'error';
}) {
    const Icon = variant === 'success' ? CircleCheck : CircleAlert;
    const variants = {
        success:
            'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300',
        error: 'border-red-500/40 bg-red-500/10 text-red-800 dark:text-red-300',
    };
    return (
        <div
            role={variant === 'error' ? 'alert' : 'status'}
            className={`rounded-xl border p-4 ${variants[variant]}`}
        >
            <div className="flex items-start gap-3">
                <Icon className="size-5 shrink-0" aria-hidden="true" />
                <div>
                    <p className="text-sm font-medium">{message}</p>
                </div>
            </div>
        </div>
    );
}
