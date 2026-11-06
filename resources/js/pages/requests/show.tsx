import { Head } from '@inertiajs/react';
import { RequestStatusBadge } from '@/components/request-status-badge';
import { index } from '@/routes/requests';
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

export default function RequestsShow({ request }: { request: RequestShow }) {
    return (
        <>
            <Head title={request.title} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
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
