// Plan 030: the files of a proposal or a message. Rows follow a brief's files; the picker that uses
// FILE_TYPES adapts TailAdmin form/input/FileInput.tsx as the brief editor does.
import { Paperclip } from 'lucide-react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import { FileSize } from '@/pages/discovery/shared';
import type { Attachment } from '@/pages/discovery/shared';
import '../../css/elancer-files.css';

// What the server accepts on a proposal or a message. It checks again; these only save a wasted upload.
export const FILE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'zip'];
export const FILE_BYTES = 5 * 1024 * 1024;
export const FILE_ACCEPT = FILE_TYPES.map((type) => `.${type}`).join(',');

/** The sentence the server would answer with, or null when the file may be sent. */
export function useFileProblem() {
    const { t } = useTranslation();
    return (file: File): string | null => {
        const ending = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!FILE_TYPES.includes(ending))
            return t('Attach a JPG, PNG, WebP, PDF, TXT or ZIP file.');
        if (file.size > FILE_BYTES) return t('Each file can be up to 5 MB.');
        return null;
    };
}

/** Each file is a download, never shown inside the site. */
export function AttachedFiles({
    files,
    href,
    label,
    remove,
}: {
    files: Attachment[];
    href: (file: Attachment) => string;
    label?: string;
    remove?: {
        label: (file: Attachment) => string;
        disabled: boolean;
        onClick: (file: Attachment) => void;
    };
}) {
    const { t } = useTranslation();
    if (!files.length) return null;
    return (
        <ul
            className="attached-files"
            aria-label={label ?? t('Attached files')}
        >
            {files.map((file) => (
                <li key={file.id}>
                    <Paperclip size={16} aria-hidden="true" />
                    <a href={href(file)} download>
                        <bdi>{file.name}</bdi>
                    </a>
                    <span className="attached-files-size">
                        <FileSize bytes={file.size} />
                    </span>
                    {remove && (
                        <Button
                            variant="outline"
                            disabled={remove.disabled}
                            aria-label={remove.label(file)}
                            onClick={() => remove.onClick(file)}
                        >
                            {t('Remove')}
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}
