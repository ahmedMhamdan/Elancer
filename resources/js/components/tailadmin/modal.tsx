import { useTranslation } from '@/hooks/use-translation';
// Adapted from local TailAdmin src/components/ui/modal/index.tsx (MIT).
// Retains blurred backdrop, rounded panel, close control and content wrapper.
// Radix supplies focus trapping/restoration, scroll lock and Escape semantics.
import { cn } from '@/lib/utils';
import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
export default function Modal({
    open,
    onClose,
    title,
    description,
    children,
    className = '',
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    children: ReactNode;
    className?: string;
}) {
    const { t } = useTranslation();

    return (
        <Dialog.Root
            open={open}
            onOpenChange={(value) => {
                if (!value) onClose();
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[100000] bg-black/45 backdrop-blur-sm" />
                <Dialog.Content
                    className={cn(
                        'bg-card text-card-foreground fixed start-1/2 top-1/2 z-[100001] max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-3xl p-5 shadow-xl sm:p-8 rtl:translate-x-1/2',
                        className,
                    )}
                >
                    <Dialog.Close className="bg-muted text-muted-foreground focus-visible:outline-ring hover:bg-accent absolute end-3 top-3 flex size-11 items-center justify-center rounded-full focus-visible:outline-2">
                        <X size={20} aria-hidden="true" />
                        <span className="sr-only">{t('Close')}</span>
                    </Dialog.Close>
                    <Dialog.Title className="pe-10 text-2xl font-semibold tracking-tight">
                        {title}
                    </Dialog.Title>
                    <Dialog.Description className="text-muted-foreground mt-2 pe-8 text-sm">
                        {description}
                    </Dialog.Description>
                    <div className="mt-6">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
