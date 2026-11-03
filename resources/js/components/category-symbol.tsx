import {
    BarChart3,
    BriefcaseBusiness,
    Code2,
    Cuboid,
    Layers,
    Megaphone,
    Pencil,
    Type,
    Video,
} from 'lucide-react';
import { workKind } from '@/components/home/project-art';

const symbols = {
    development: Code2,
    design: Pencil,
    data: BarChart3,
    business: BriefcaseBusiness,
    writing: Type,
    video: Video,
    marketing: Megaphone,
    engineering: Cuboid,
    general: Layers,
};

export default function CategorySymbol({ name }: { name: string }) {
    const Symbol = symbols[workKind(name)];
    return <Symbol aria-hidden="true" strokeWidth={1.6} />;
}
