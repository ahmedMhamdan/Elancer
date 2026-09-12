// Adapts TailAdmin BasicTableOne and DefaultInputs through the existing table,
// ComponentCard, TextArea and Button adaptations. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ComponentCard from '@/components/component-card';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import TextArea from '@/components/tailadmin/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/components/tailadmin/table';
type Submission = {
    id: number;
    name: string;
    email: string;
    status: string;
    reason: string | null;
};
function Review({
    submission,
    close,
}: {
    submission: Submission;
    close: () => void;
}) {
    const form = useForm({ reason: '' });
    const [error, setError] = useState('');
    function decide(status: 'approved' | 'rejected') {
        if (
            !window.confirm(
                status === 'approved'
                    ? 'Approve this identity submission?'
                    : 'Reject this submission and request new images?',
            )
        )
            return;
        form.transform((data) => ({ ...data, status }));
        form.put(`/admin/identity/${submission.id}`, {
            preserveScroll: true,
            onSuccess: close,
            onHttpException: () => {
                setError(
                    'The submission could not be reviewed. You cannot review your own identity. Refresh and try again.',
                );
                return false;
            },
        });
    }
    return (
        <ComponentCard
            title={`Review ${submission.name}`}
            desc="Compare the ID and selfie before recording your decision. Images are removed after review."
        >
            <div className="grid gap-4 md:grid-cols-2">
                {(['id', 'selfie'] as const).map((kind) => (
                    <figure key={kind}>
                        <figcaption className="mb-2 font-medium">
                            {kind === 'id' ? 'Government ID' : 'Selfie'}
                        </figcaption>
                        <img
                            src={`/admin/identity/${submission.id}/image/${kind}`}
                            alt={
                                kind === 'id'
                                    ? 'Submitted government ID'
                                    : 'Submitted selfie'
                            }
                            className="border-border h-72 w-full rounded-lg border object-contain"
                        />
                    </figure>
                ))}
            </div>
            <div>
                <Label htmlFor="identity-review-reason">
                    Review reason (shared with the user)
                </Label>
                <TextArea
                    id="identity-review-reason"
                    rows={3}
                    value={form.data.reason}
                    maxLength={1000}
                    onChange={(value) => form.setData('reason', value)}
                />
                <InputError message={form.errors.reason} />
            </div>
            {error && (
                <p role="alert" className="text-destructive">
                    {error}
                </p>
            )}
            <div className="flex flex-wrap gap-3">
                <Button
                    disabled={form.processing || !form.data.reason.trim()}
                    onClick={() => decide('approved')}
                >
                    Approve identity
                </Button>
                <Button
                    disabled={form.processing || !form.data.reason.trim()}
                    variant="outline"
                    onClick={() => decide('rejected')}
                >
                    Request resubmission
                </Button>
                <Button
                    variant="outline"
                    disabled={form.processing}
                    onClick={close}
                >
                    Close
                </Button>
            </div>
        </ComponentCard>
    );
}
export default function IdentityReviews({
    submissions,
}: {
    submissions: {
        data: Submission[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const [selected, setSelected] = useState<Submission | null>(null);
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title="Identity reviews" />
            <div className="workspace-page-heading">
                <h1>Identity reviews</h1>
                <p>Private document review for clients and freelancers.</p>
            </div>
            {selected && (
                <Review
                    key={selected.id}
                    submission={selected}
                    close={() => {
                        setSelected(null);
                        router.reload({ only: ['submissions'] });
                    }}
                />
            )}
            <div className="border-border bg-card overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {['Account', 'Status', 'Review'].map((label) => (
                                <TableCell
                                    key={label}
                                    isHeader
                                    className="px-5 py-3 text-start"
                                >
                                    {label}
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {submissions.data.map((row) => (
                            <TableRow
                                key={row.id}
                                className="border-border border-t"
                            >
                                <TableCell className="px-5 py-4">
                                    <p>{row.name}</p>
                                    <p className="text-muted-foreground text-sm break-all">
                                        {row.email}
                                    </p>
                                </TableCell>
                                <TableCell className="px-5 py-4">
                                    {row.status}
                                </TableCell>
                                <TableCell className="px-5 py-4">
                                    {row.status === 'pending' ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setSelected(row)}
                                        >
                                            Review documents
                                        </Button>
                                    ) : (
                                        <span className="text-muted-foreground text-sm">
                                            {row.reason}
                                        </span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {!submissions.data.length && (
                            <TableRow>
                                <TableCell
                                    colSpan={3}
                                    className="text-muted-foreground p-8 text-center"
                                >
                                    No identity submissions yet.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <nav aria-label="Pagination" className="flex gap-4">
                {submissions.prev_page_url && (
                    <Link
                        href={submissions.prev_page_url}
                        onClick={() => setSelected(null)}
                        className="text-primary min-h-11 py-3 underline"
                    >
                        Previous
                    </Link>
                )}
                {submissions.next_page_url && (
                    <Link
                        href={submissions.next_page_url}
                        onClick={() => setSelected(null)}
                        className="text-primary min-h-11 py-3 underline"
                    >
                        Next
                    </Link>
                )}
            </nav>
        </div>
    );
}
