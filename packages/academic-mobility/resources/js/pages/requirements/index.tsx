import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Requirements } from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { useDeleteRequirement, useRequirements } from '@academic/hooks/use-requirements';
import { PARTICIPANT_LABELS, enumLabel } from '@academic/types';
import type { MobilityDirection, MobilityScope, ParticipantKind } from '@academic/types';

const BASE = '/academic-mobility/requirements';
const SEARCH_DELAY_MS = 300;
const selectClass = 'h-9 rounded-md border bg-background px-3 text-sm';

export default function RequirementsIndex() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [direction, setDirection] = useState<MobilityDirection | ''>('');
    const [scope, setScope] = useState<MobilityScope | ''>('');
    const [kind, setKind] = useState<ParticipantKind | ''>('');
    const deletion = useDeleteRequirement();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search.trim());
            setPage(1);
        }, SEARCH_DELAY_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const { data, isPending, isError, error, isPlaceholderData } = useRequirements({
        page,
        search: debouncedSearch || undefined,
        direction: direction || undefined,
        scope: scope || undefined,
        participant_kind: kind || undefined,
    });

    const meta = data?.meta;

    const destroy = (id: string, name: string) => {
        if (!window.confirm(t('Delete :name?', { name }))) {
            return;
        }

        deletion.mutate(id, {
            onSuccess: (response) => toast.success(response.message ?? t('Requirement deleted.')),
            onError: (failure) => toast.error(failure.message),
        });
    };

    const any = <span className="text-muted-foreground">{t('Any')}</span>;

    return (
        <>
            <Head title={t('Requirements')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">{t('Requirements')}</h1>
                        <p className="text-sm text-muted-foreground">
                            {t('A mobility brings every requirement that matches its direction, scope and participant type; an empty column matches any.')}
                        </p>
                    </div>
                    {can(Requirements.create) && (
                        <Button asChild>
                            <Link href={`${BASE}/create`}>{t('New requirement')}</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by name')}
                        aria-label={t('Search requirements')}
                        className="sm:max-w-xs"
                    />
                    <select
                        value={direction}
                        onChange={(event) => {
                            setDirection(event.target.value as MobilityDirection | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by direction')}
                        className={selectClass}
                    >
                        <option value="">{t('Any direction')}</option>
                        <option value="outgoing">{t('Outgoing')}</option>
                        <option value="incoming">{t('Incoming')}</option>
                    </select>
                    <select
                        value={scope}
                        onChange={(event) => {
                            setScope(event.target.value as MobilityScope | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by scope')}
                        className={selectClass}
                    >
                        <option value="">{t('Any scope')}</option>
                        <option value="national">{t('National')}</option>
                        <option value="international">{t('International')}</option>
                    </select>
                    <select
                        value={kind}
                        onChange={(event) => {
                            setKind(event.target.value as ParticipantKind | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by participant type')}
                        className={selectClass}
                    >
                        <option value="">{t('Any participant')}</option>
                        {(Object.keys(PARTICIPANT_LABELS) as ParticipantKind[]).map((value) => (
                            <option key={value} value={value}>
                                {t(PARTICIPANT_LABELS[value])}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">{t('Requirement')}</th>
                                <th className="p-3 font-medium">{t('Direction')}</th>
                                <th className="p-3 font-medium">{t('Scope')}</th>
                                <th className="p-3 font-medium">{t('Participant type')}</th>
                                <th className="p-3 font-medium">{t('Type')}</th>
                                <th className="p-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody className={isPlaceholderData ? 'opacity-60' : undefined}>
                            {isPending && (
                                <tr>
                                    <td colSpan={6} className="p-6 text-center text-muted-foreground">
                                        {t('Loading…')}
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
                                        {t('No requirements found.')}
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((requirement) => (
                                <tr key={requirement.id} className={requirement.is_active ? 'border-t' : 'border-t opacity-60'}>
                                    <td className="p-3">
                                        <span className="font-medium">{requirement.name}</span>
                                        {requirement.description && (
                                            <span className="block text-xs text-muted-foreground">{requirement.description}</span>
                                        )}
                                        {!requirement.is_active && (
                                            <Badge variant="secondary" className="mt-1">
                                                {t('Inactive')}
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="p-3">{requirement.direction ? t(enumLabel(requirement.direction)) : any}</td>
                                    <td className="p-3">{requirement.scope ? t(enumLabel(requirement.scope)) : any}</td>
                                    <td className="p-3">
                                        {requirement.participant_kind ? t(PARTICIPANT_LABELS[requirement.participant_kind]) : any}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={requirement.is_mandatory ? 'default' : 'secondary'}>
                                            {requirement.is_mandatory ? t('Mandatory') : t('Optional')}
                                        </Badge>
                                    </td>
                                    <td className="flex justify-end gap-2 p-3">
                                        {can(Requirements.update) && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`${BASE}/${requirement.id}/edit`}>{t('Edit')}</Link>
                                            </Button>
                                        )}
                                        {can(Requirements.destroy) && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={deletion.isPending}
                                                onClick={() => destroy(requirement.id, requirement.name)}
                                            >
                                                {t('Delete')}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {t('Page :page of :last', { page: meta.current_page, last: meta.last_page })}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>
                                {t('Previous')}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={page >= meta.last_page || isPlaceholderData}
                                onClick={() => setPage((current) => current + 1)}
                            >
                                {t('Next')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

RequirementsIndex.layout = {
    breadcrumbs: [{ title: 'Requirements', href: BASE }],
};
