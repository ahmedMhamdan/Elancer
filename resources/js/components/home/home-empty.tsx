// Q33: when there is nothing real to show, the homepage says so and offers the next step.
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';

export default function HomeEmpty({
    title,
    text,
    action,
    href,
}: {
    title: string;
    text: string;
    action: string;
    href: string;
}) {
    return (
        <div className="home-empty">
            <h3>{title}</h3>
            <p>{text}</p>
            <Link href={href} className="elancer-outline-button">
                {action}
                <ArrowUpRight size={16} aria-hidden="true" />
            </Link>
        </div>
    );
}
