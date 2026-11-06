import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { RequestStatusBadge } from '@/components/request-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy, edit, index } from '@/routes/requests';
import type { RequestDetail, RequestShow } from '@/types';

function formatValue(detail: RequestDetail): string {
    if (detail.value === null || detail.value === '') {
        return '—';
    }

    if (detail.type === 'boolean') {
        return detail.value ? 'Yes' : 'No';
    }

    if (detail.type === 'date' || detail.type === 'datetime') {
        const date = new Date(String(detail.value));

        return detail.type === 'date'
            ? date.toLocaleDateString()
            : date.toLocaleString();
    }

    return String(detail.value);
}

export default function RequestsShow({
    request,
    canEdit,
    canDelete,
}: {
    request: RequestShow;
    canEdit: boolean;
    canDelete: boolean;
}) {
    return (
        <>
            <Head title={request.title} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {request.title}
                            </h1>
                            <RequestStatusBadge status={request.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {request.type} · requested by {request.requester} ·{' '}
                            <span className="capitalize">{request.priority}</span>{' '}
                            priority
                        </p>
                        {request.description && (
                            <p className="text-sm">{request.description}</p>
                        )}
                    </div>

                    <div className="flex shrink-0 gap-2">
                        {canEdit && (
                            <Button variant="outline" asChild>
                                <Link href={edit(request.uuid)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}

                        {canDelete && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 /> Delete
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Delete request?</DialogTitle>
                                        <DialogDescription>
                                            &ldquo;{request.title}&rdquo; will be
                                            removed from your requests.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form action={destroy(request.uuid)}>
                                        {({ processing }) => (
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    Delete
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                    {request.details.map((detail) => (
                        <div key={detail.label}>
                            <dt className="text-sm text-muted-foreground">
                                {detail.label}
                            </dt>
                            <dd className="text-sm font-medium whitespace-pre-line">
                                {formatValue(detail)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </>
    );
}

RequestsShow.layout = {
    breadcrumbs: [{ title: 'Requests', href: index() }],
};
