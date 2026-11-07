import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Mobilities } from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';
import { useMobilities } from '@academic/hooks/use-mobilities';
import { MOBILITY_STATUSES, PARTICIPANT_LABELS, enumLabel } from '@academic/types';
import type { MobilityDirection, ParticipantKind, MobilityScope, MobilitySortColumn, MobilityStatus } from '@academic/types';
import { useTranslation } from '@/hooks/use-translation';

type Sort = { column: MobilitySortColumn; direction: 'asc' | 'desc' };

export default function MobilitiesIndex() {
    const { t, locale } = useTranslation();
    const { can } = usePermissions();
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState<MobilityStatus | ''>('');
    const [participantType, setParticipantType] = useState<ParticipantKind | ''>('');
    const [direction, setDirection] = useState<MobilityDirection | ''>('');
    const [scope, setScope] = useState<MobilityScope | ''>('');
    const [sort, setSort] = useState<Sort>({ column: 'created_at', direction: 'desc' });

    const { data, isPending, isError, error, isPlaceholderData } = useMobilities({
        page,
        status: status || undefined,
        participant_type: participantType || undefined,
        direction: direction || undefined,
        scope: scope || undefined,
        with: ['originInstitution', 'destinationInstitution'],
        sort: { [sort.column]: sort.direction },
    });

    const meta = data?.meta;

    const toggleSort = (column: MobilitySortColumn) => {
        setSort((current) => ({
            column,
            direction: current.column === column && current.direction === 'desc' ? 'asc' : 'desc',
        }));
        setPage(1);
    };

    const arrow = (column: MobilitySortColumn) =>
        sort.column === column ? (sort.direction === 'asc' ? ' ↑' : ' ↓') : '';
    const ariaSort = (column: MobilitySortColumn) =>
        sort.column === column
            ? sort.direction === 'asc'
                ? ('ascending' as const)
                : ('descending' as const)
            : undefined;

    const sortable = (column: MobilitySortColumn, title: string) => (
        <th className="p-3 font-medium" aria-sort={ariaSort(column)}>
            <button type="button" onClick={() => toggleSort(column)} className="font-medium">
                {title}
                {arrow(column)}
            </button>
        </th>
    );

    return (
        <>
            <Head title={t('Academic mobility')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">{t('Academic mobility')}</h1>
                    {can(Mobilities.create) && (
                        <Button asChild>
                            <Link href="/academic-mobility/mobilities/create">{t('New mobility')}</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <select
                        value={participantType}
                        onChange={(event) => {
                            setParticipantType(event.target.value as ParticipantKind | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by participant type')}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">{t('Any participant')}</option>
                        {(Object.keys(PARTICIPANT_LABELS) as ParticipantKind[]).map((kind) => (
                            <option key={kind} value={kind}>
                                {t(PARTICIPANT_LABELS[kind])}
                            </option>
                        ))}
                    </select>
                    <select
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value as MobilityStatus | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by status')}
                        className="h-9 rounded-md border bg-background px-3 text-sm capitalize"
                    >
                        <option value="">{t('All statuses')}</option>
                        {MOBILITY_STATUSES.map((value) => (
                            <option key={value} value={value}>
                                {t(enumLabel(value))}
                            </option>
                        ))}
                    </select>
                    <select
                        value={direction}
                        onChange={(event) => {
                            setDirection(event.target.value as MobilityDirection | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by direction')}
                        className="h-9 rounded-md border bg-background px-3 text-sm capitalize"
                    >
                        <option value="">{t('Any direction')}</option>
                        <option value="incoming">{t('Incoming')}</option>
                        <option value="outgoing">{t('Outgoing')}</option>
                    </select>
                    <select
                        value={scope}
                        onChange={(event) => {
                            setScope(event.target.value as MobilityScope | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by scope')}
                        className="h-9 rounded-md border bg-background px-3 text-sm capitalize"
                    >
                        <option value="">{t('Any scope')}</option>
                        <option value="national">{t('National')}</option>
                        <option value="international">{t('International')}</option>
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">ID</th>
                                {sortable('participant_type', t('Participant'))}
                                {sortable('direction', t('Direction'))}
                                {sortable('scope', t('Scope'))}
                                <th className="p-3 font-medium">{t('Route')}</th>
                                {sortable('strategy', t('Strategy'))}
                                {sortable('status', t('Status'))}
                                {sortable('created_at', t('Created'))}
                            </tr>
                        </thead>
                        <tbody className={isPlaceholderData ? 'opacity-60' : undefined}>
                            {isPending && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        {t('Loading…')}
                                    </td>
                                </tr>
                            )}
                            {isError && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-red-600 dark:text-red-400">
                                        {error.message}
                                    </td>
                                </tr>
                            )}
                            {data?.items.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        {t('No mobilities found.')}
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((mobility) => (
                                <tr key={mobility.id} className="border-t">
                                    <td className="p-3 font-mono text-xs">
                                        <Link href={`/academic-mobility/mobilities/${mobility.id}`} className="underline-offset-4 hover:underline">
                                            {mobility.id}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        <span>{t(PARTICIPANT_LABELS[mobility.participant_type] ?? mobility.participant_type)}</span>{' '}
                                        <span className="text-muted-foreground">#{mobility.participant_id}</span>
                                    </td>
                                    <td className="p-3 capitalize">{mobility.direction ? t(enumLabel(mobility.direction)) : '—'}</td>
                                    <td className="p-3 capitalize">{mobility.scope ? t(enumLabel(mobility.scope)) : '—'}</td>
                                    <td className="p-3">
                                        {mobility.origin && mobility.destination
                                            ? `${mobility.origin.name} → ${mobility.destination.name}`
                                            : '—'}
                                    </td>
                                    <td className="p-3">{mobility.strategy}</td>
                                    <td className="p-3">
                                        <Badge variant={mobility.is_final ? 'secondary' : 'default'} className="capitalize">
                                            {t(enumLabel(mobility.status))}
                                        </Badge>
                                    </td>
                                    <td className="p-3">
                                        {mobility.created_at
                                            ? new Date(mobility.created_at).toLocaleDateString(locale)
                                            : '—'}
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
                            <Button
                                variant="outline"
                                disabled={page <= 1}
                                onClick={() => setPage((current) => current - 1)}
                            >
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

MobilitiesIndex.layout = {
    breadcrumbs: [{ title: 'Academic mobility', href: '/academic-mobility/mobilities' }],
};
