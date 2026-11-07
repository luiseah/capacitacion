import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { RequestStatusBadge } from '@/components/request-status-badge';
import { Button } from '@/components/ui/button';
import { Requests } from '@/generated/permissions';
import { useRequests } from '@/hooks/requests/use-request-queries';
import { usePermissions } from '@/hooks/use-permissions';
import { create, index, show } from '@/routes/requests';

export default function RequestsIndex() {
    const { can } = usePermissions();
    const [page, setPage] = useState(1);
    const { data, isPending, isError, error, isPlaceholderData } = useRequests({ page, with: ['type', 'requester'] });

    const meta = data?.meta;

    return (
        <>
            <Head title="Requests" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Requests
                    </h1>
                    {can(Requests.create) && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New request
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Title</th>
                                <th className="p-3 font-medium">Type</th>
                                <th className="p-3 font-medium">Requester</th>
                                <th className="p-3 font-medium">Priority</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody
                            className={isPlaceholderData ? 'opacity-60' : undefined}
                        >
                            {isPending && (
                                <tr>
                                    <td colSpan={6} className="p-6 text-center text-muted-foreground">
                                        Loading…
                                    </td>
                                </tr>
                            )}
                            {isError && (
                                <tr>
                                    <td colSpan={6} className="p-6 text-center text-red-600 dark:text-red-400">
                                        {error.message}
                                    </td>
                                </tr>
                            )}
                            {data?.items.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-6 text-center text-muted-foreground">
                                        No requests yet.
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((item) => (
                                <tr key={item.uuid} className="border-t">
                                    <td className="p-3">
                                        <Link
                                            href={show(item.uuid)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {item.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">{item.type}</td>
                                    <td className="p-3">{item.requester}</td>
                                    <td className="p-3 capitalize">{item.priority}</td>
                                    <td className="p-3">
                                        <RequestStatusBadge status={item.status} />
                                    </td>
                                    <td className="p-3">
                                        {new Date(item.created_at).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {meta.current_page} of {meta.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={page <= 1}
                                onClick={() => setPage((current) => current - 1)}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                disabled={page >= meta.last_page || isPlaceholderData}
                                onClick={() => setPage((current) => current + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

RequestsIndex.layout = {
    breadcrumbs: [{ title: 'Requests', href: index() }],
};
