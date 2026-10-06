import { ExternalLink } from 'lucide-react';
import type { WorkLink } from '@/components/freelancer-card';
import { useTranslation } from '@/hooks/use-translation';

export type CaseContent = {
    title: string;
    summary: string;
    body: string;
    skills: string[];
    links: WorkLink[];
    // Absent on cases saved before images existed and on cases without images.
    images?: CaseImage[];
};
export type CaseImage = { id: number; alt: string };

// Served per request, so a hidden or withdrawn case stops showing its images at once.
export const caseImageUrl = (id: number) => `/portfolio/images/${id}`;

// One rendering of a case study, so the client approves exactly what the public page shows.
export function CaseBody({ content }: { content: CaseContent }) {
    const { t } = useTranslation();
    return (
        <div className="market-stack">
            <p dir="auto" className="talent-headline">
                {content.summary}
            </p>
            <p dir="auto" className="market-prose break-words">
                {content.body}
            </p>
            {content.images && content.images.length > 0 && (
                <ul className="case-images">
                    {content.images.map((image) => (
                        <li key={image.id}>
                            <a
                                href={caseImageUrl(image.id)}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    src={caseImageUrl(image.id)}
                                    alt={image.alt}
                                    loading="lazy"
                                />
                            </a>
                        </li>
                    ))}
                </ul>
            )}
            {content.skills.length > 0 && (
                <div className="job-skills" aria-label={t('Skills')}>
                    {content.skills.map((skill) => (
                        <span key={skill} dir="auto">
                            {skill}
                        </span>
                    ))}
                </div>
            )}
            {content.links.length > 0 && (
                <ul className="talent-links">
                    {content.links.map((link, i) => (
                        <li key={i}>
                            <a
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <span dir="auto">{link.label}</span>
                                <ExternalLink
                                    size={16}
                                    aria-label={t('Opens in a new tab')}
                                />
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
