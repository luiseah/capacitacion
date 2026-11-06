import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { RequestStatusBadge } from '@/components/request-status-badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/requests';
import type { Paginated, RequestSummary } from '@/types';

export default function RequestsIndex({
    requests,
    canCreate,
}: {
    requests: Paginated<RequestSummary>;
    canCreate: boolean;
}) {
    return (
        <>
            <Head title="Requests" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Requests
                    </h1>
                    {canCreate && (
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
                        <tbody>
                            {requests.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No requests yet.
                                    </td>
                                </tr>
                            )}
                            {requests.data.map((item) => (
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
                                    <td className="p-3 capitalize">
                                        {item.priority}
                                    </td>
                                    <td className="p-3">
                                        <RequestStatusBadge
                                            status={item.status}
                                        />
                                    </td>
                                    <td className="p-3">
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {requests.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {requests.current_page} of {requests.last_page}
                        </span>
                        <div className="flex gap-2">
                            {requests.prev_page_url && (
                                <Button variant="outline" asChild>
                                    <Link href={requests.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {requests.next_page_url && (
                                <Button variant="outline" asChild>
                                    <Link href={requests.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
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
