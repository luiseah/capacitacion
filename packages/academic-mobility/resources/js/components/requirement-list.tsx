import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Item = {
    id: string;
    name: string;
    description: string | null;
    is_mandatory: boolean;
    fulfilled?: boolean;
};

/**
 * What a mobility has to bring: the preview of the creation form and the checklist of an existing request.
 * `fulfilled` is only known for requests that exist.
 */
export function RequirementList({ items, empty }: { items: Item[]; empty: string }) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border">
            {items.map((item) => (
                <li key={item.id} className="flex items-start gap-3 p-3 text-sm">
                    <span
                        className={cn(
                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border',
                            item.fulfilled && 'border-primary bg-primary text-primary-foreground',
                        )}
                        aria-hidden
                    >
                        {item.fulfilled && <Check className="size-3" />}
                    </span>
                    <span className="flex-1">
                        <span className="font-medium">{item.name}</span>
                        {item.description && <span className="block text-xs text-muted-foreground">{item.description}</span>}
                    </span>
                    <Badge variant={item.is_mandatory ? 'default' : 'secondary'}>
                        {item.is_mandatory ? t('Mandatory') : t('Optional')}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}
