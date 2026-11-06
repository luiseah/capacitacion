import { Badge } from '@/components/ui/badge';
import type { RequestStatus } from '@/types';

const variants: Record<
    RequestStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    submitted: 'secondary',
    under_review: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    cancelled: 'outline',
};

export function RequestStatusBadge({ status }: { status: RequestStatus }) {
    return (
        <Badge variant={variants[status]} className="capitalize">
            {status.replace('_', ' ')}
        </Badge>
    );
}
