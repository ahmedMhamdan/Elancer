// Adapted from TailAdmin/src/components/common/ComponentCard.tsx (MIT).
// Original copyright and license: THIRD_PARTY_NOTICES.md.
import type { ReactNode } from 'react';

interface ComponentCardProps {
    title: string;
    children: ReactNode;
    className?: string;
    desc?: string;
}

export default function ComponentCard({
    title,
    children,
    className = '',
    desc = '',
}: ComponentCardProps) {
    return (
        <div
            className={`border-border bg-card rounded-2xl border ${className}`}
        >
            <div className="px-6 py-5">
                <h2 className="text-card-foreground text-base font-medium">
                    {title}
                </h2>
                {desc && (
                    <p className="text-muted-foreground mt-1 text-sm">{desc}</p>
                )}
            </div>
            <div className="border-border border-t p-4 sm:p-6">
                <div className="space-y-6">{children}</div>
            </div>
        </div>
    );
}
