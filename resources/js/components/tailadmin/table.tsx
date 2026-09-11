// Adapted from TailAdmin src/components/ui/table/index.tsx (MIT).
// Retains semantic wrappers; adds native cell attributes for scope and empty rows.
// See THIRD_PARTY_NOTICES.md.
import type { ReactNode, TdHTMLAttributes } from 'react';

type Props = { children: ReactNode; className?: string };
const Table = ({ children, className = '' }: Props) => (
    <table className={`min-w-full ${className}`}>{children}</table>
);
const TableHeader = ({ children, className }: Props) => (
    <thead className={className}>{children}</thead>
);
const TableBody = ({ children, className }: Props) => (
    <tbody className={className}>{children}</tbody>
);
const TableRow = ({ children, className }: Props) => (
    <tr className={className}>{children}</tr>
);
const TableCell = ({
    children,
    isHeader = false,
    className,
    ...props
}: TdHTMLAttributes<HTMLTableCellElement> & { isHeader?: boolean }) => {
    const CellTag = isHeader ? 'th' : 'td';
    return (
        <CellTag
            {...props}
            scope={isHeader ? 'col' : undefined}
            className={className}
        >
            {children}
        </CellTag>
    );
};
export { Table, TableHeader, TableBody, TableRow, TableCell };
