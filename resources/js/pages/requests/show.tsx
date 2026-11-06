import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
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
import { useDeleteRequest } from '@/hooks/requests/use-request-mutations';
import { useRequest } from '@/hooks/requests/use-request-queries';
import { edit, index } from '@/routes/requests';
import type { ApiRequestDetail } from '@/types';

type Detail = ApiRequestDetail['details'][number];

function formatValue(detail: Detail): string {
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

export default function RequestsShow({ requestUuid }: { requestUuid: string }) {
    const { data: request, isPending, isError, error } = useRequest(requestUuid);
    const deletion = useDeleteRequest();

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">Loading…</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    const destroy = () =>
        deletion.mutate(request.uuid, {
            onSuccess: (response) => {
                toast.success(response.message ?? 'Request deleted.');
                router.visit(index());
            },
            onError: (failure) => toast.error(failure.message),
        });

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
                        {request.abilities.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(request.uuid)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}

                        {request.abilities.delete && (
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
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            disabled={deletion.isPending}
                                            onClick={destroy}
                                        >
                                            Delete
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                    {request.details.map((detail) => (
                        <div key={detail.key}>
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
