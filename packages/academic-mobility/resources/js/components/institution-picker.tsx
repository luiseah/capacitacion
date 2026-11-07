import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { useInstitutionOptions } from '@academic/hooks/use-mobilities';
import type { Institution, MobilityScope } from '@academic/types';

const SEARCH_DELAY_MS = 300;

/**
 * Searchable dropdown of the partner institutions of a scope. The server does the searching
 * (name or country, any word order), so it stays fast with hundreds of institutions.
 */
export function InstitutionPicker({
    id,
    scope,
    value,
    onChange,
    anyScope = false,
    invalid = false,
    placeholder,
}: {
    id?: string;
    scope: MobilityScope | '';
    value: Institution | null;
    onChange: (institution: Institution) => void;
    /** Search every partner, whatever the scope (the agreements form has no scope). */
    anyScope?: boolean;
    invalid?: boolean;
    /** What the closed picker says while nothing is chosen. */
    placeholder?: string;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(search.trim()), SEARCH_DELAY_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const { data, isFetching } = useInstitutionOptions(scope, debounced, open, anyScope);

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setSearch('');
                }
            }}
        >
            <DropdownMenuTrigger asChild>
            <Button
                id={id}
                type="button"
                variant="outline"
                disabled={scope === '' && !anyScope}
                className={cn('w-full justify-between font-normal', invalid && 'border-red-500')}
            >
                {value ? (
                    <span>
                        {value.name} <span className="text-muted-foreground">({value.country})</span>
                    </span>
                ) : (
                    <span className="text-muted-foreground">
                        {scope === '' && !anyScope ? t('Choose the scope first') : (placeholder ?? t('Search an institution…'))}
                    </span>
                )}
                <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
            </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-(--radix-dropdown-menu-trigger-width) min-w-72 p-0"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <div className="border-b p-2">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        // Typing must not trigger the menu's own type-ahead.
                        onKeyDown={(event) => event.stopPropagation()}
                        placeholder={t('Search by name or country')}
                        aria-label={t('Search institutions')}
                        autoFocus
                    />
                </div>
                <div className={cn('max-h-64 overflow-auto p-1', isFetching && 'opacity-60')}>
                    {data?.length === 0 && <p className="p-3 text-sm text-muted-foreground">{t('No institutions found.')}</p>}
                    {data?.map((institution) => (
                        <DropdownMenuItem key={institution.id} onSelect={() => onChange(institution)}>
                            <span className="flex-1">{institution.name}</span>
                            <span className="text-xs text-muted-foreground">{institution.country}</span>
                            {value?.id === institution.id && <Check className="size-4" aria-hidden />}
                        </DropdownMenuItem>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
