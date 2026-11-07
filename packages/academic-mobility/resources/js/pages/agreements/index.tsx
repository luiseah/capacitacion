import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Agreements } from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { ActivityBadges, AgreementStatusBadge, useValidity } from '@academic/components/agreement-list';
import { InstitutionPicker } from '@academic/components/institution-picker';
import { useAgreements, useDeleteAgreement } from '@academic/hooks/use-agreements';
import { agreementDocumentUrl } from '@academic/services/MobilityService';
import { AGREEMENT_ACTIVITIES, AGREEMENT_ACTIVITY_LABELS, AGREEMENT_RENEWAL_LABELS, AGREEMENT_STATUSES, AGREEMENT_STATUS_LABELS, enumLabel } from '@academic/types';
import type { AgreementActivity, AgreementParties, AgreementStatus, AgreementType, Institution, MobilityScope } from '@academic/types';

const BASE = '/academic-mobility/agreements';
const SEARCH_DELAY_MS = 300;
const selectClass = 'h-9 rounded-md border bg-background px-3 text-sm';

export default function AgreementsIndex() {
    const { t, locale } = useTranslation();
    const { can } = usePermissions();
    const validity = useValidity();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [institution, setInstitution] = useState<Institution | null>(null);
    const [type, setType] = useState<AgreementType | ''>('');
    const [parties, setParties] = useState<AgreementParties | ''>('');
    const [status, setStatus] = useState<AgreementStatus | ''>('');
    const [scope, setScope] = useState<MobilityScope | ''>('');
    const [activity, setActivity] = useState<AgreementActivity | ''>('');
    const deletion = useDeleteAgreement();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search.trim());
            setPage(1);
        }, SEARCH_DELAY_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const { data, isPending, isError, error, isPlaceholderData } = useAgreements({
        page,
        search: debouncedSearch || undefined,
        institution_id: institution?.id,
        type: type || undefined,
        parties: parties || undefined,
        status: status || undefined,
        scope: scope || undefined,
        activity: activity || undefined,
    });

    const meta = data?.meta;

    const destroy = (id: string, name: string) => {
        if (!window.confirm(t('Delete :name?', { name }))) {
            return;
        }

        deletion.mutate(id, {
            onSuccess: (response) => toast.success(response.message ?? t('Agreement deleted.')),
            onError: (failure) => toast.error(failure.message),
        });
    };

    return (
        <>
            <Head title={t('Agreements')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">{t('Agreements')}</h1>
                        <p className="text-sm text-muted-foreground">
                            {t('The agreements of the partner institutions with the local one.')}
                        </p>
                    </div>
                    {can(Agreements.create) && (
                        <Button asChild>
                            <Link href={`${BASE}/create`}>{t('New agreement')}</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-2 lg:flex-row">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by name or reference')}
                        aria-label={t('Search agreements')}
                        className="lg:max-w-xs"
                    />
                    <div className="lg:w-72">
                        <InstitutionPicker
                            scope=""
                            anyScope
                            value={institution}
                            onChange={(chosen) => {
                                setInstitution(chosen);
                                setPage(1);
                            }}
                        />
                    </div>
                    {institution && (
                        <Button variant="ghost" onClick={() => setInstitution(null)}>
                            {t('All institutions')}
                        </Button>
                    )}
                    <select
                        value={type}
                        onChange={(event) => {
                            setType(event.target.value as AgreementType | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by type')}
                        className={selectClass}
                    >
                        <option value="">{t('Any type')}</option>
                        <option value="framework">{t('Framework')}</option>
                        <option value="specific">{t('Specific')}</option>
                    </select>
                    <select
                        value={parties}
                        onChange={(event) => {
                            setParties(event.target.value as AgreementParties | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by parties')}
                        className={selectClass}
                    >
                        <option value="">{t('Bilateral or multilateral')}</option>
                        <option value="bilateral">{t('Bilateral')}</option>
                        <option value="multilateral">{t('Multilateral')}</option>
                    </select>
                    <select
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value as AgreementStatus | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by status')}
                        className={selectClass}
                    >
                        <option value="">{t('All statuses')}</option>
                        {AGREEMENT_STATUSES.map((value) => (
                            <option key={value} value={value}>
                                {t(AGREEMENT_STATUS_LABELS[value])}
                            </option>
                        ))}
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
                        value={activity}
                        onChange={(event) => {
                            setActivity(event.target.value as AgreementActivity | '');
                            setPage(1);
                        }}
                        aria-label={t('Filter by activity')}
                        className={selectClass}
                    >
                        <option value="">{t('Any activity')}</option>
                        {AGREEMENT_ACTIVITIES.map((value) => (
                            <option key={value} value={value}>
                                {t(AGREEMENT_ACTIVITY_LABELS[value])}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">{t('Agreement')}</th>
                                <th className="p-3 font-medium">{t('Institution')}</th>
                                <th className="p-3 font-medium">{t('Validity')}</th>
                                <th className="p-3 font-medium">{t('Activities')}</th>
                                <th className="p-3 font-medium">{t('Status')}</th>
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
                                        {t('No agreements found.')}
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((agreement) => (
                                <tr key={agreement.id} className="border-t align-top">
                                    <td className="p-3">
                                        <span className="font-medium">{agreement.name}</span>
                                        <span className="mt-1 flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">{agreement.type === 'framework' ? t('Framework') : t('Specific')}</Badge>
                                            <Badge variant="outline">{agreement.parties === 'multilateral' ? t('Multilateral') : t('Bilateral')}</Badge>
                                            <Badge variant="outline">{t(enumLabel(agreement.scope))}</Badge>
                                            {agreement.reference && (
                                                <span className="font-mono text-xs text-muted-foreground">{agreement.reference}</span>
                                            )}
                                        </span>
                                        {agreement.framework && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {t('Develops :name', { name: agreement.framework.name })}
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {agreement.institution?.name ?? '—'}
                                        {agreement.institutions && agreement.institutions.length > 0 && (
                                            <span className="block text-xs text-muted-foreground">
                                                {t('and :names', { names: agreement.institutions.map((institution) => institution.name).join(', ') })}
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {validity(agreement)}
                                        {agreement.signed_on && (
                                            <span className="block text-xs text-muted-foreground">
                                                {t('Signed :date', { date: new Date(`${agreement.signed_on}T00:00:00`).toLocaleDateString(locale) })}
                                            </span>
                                        )}
                                        <span className="block text-xs text-muted-foreground">{t(AGREEMENT_RENEWAL_LABELS[agreement.renewal])}</span>
                                    </td>
                                    <td className="p-3">
                                        <ActivityBadges agreement={agreement} />
                                    </td>
                                    <td className="p-3">
                                        <AgreementStatusBadge status={agreement.status} />
                                        {agreement.has_document && (
                                            <a
                                                href={agreementDocumentUrl(agreement.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="mt-1 block text-xs underline-offset-4 hover:underline"
                                            >
                                                {t('Signed document')}
                                            </a>
                                        )}
                                    </td>
                                    <td className="flex justify-end gap-2 p-3">
                                        {can(Agreements.update) && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`${BASE}/${agreement.id}/edit`}>{t('Edit')}</Link>
                                            </Button>
                                        )}
                                        {can(Agreements.destroy) && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={deletion.isPending}
                                                onClick={() => destroy(agreement.id, agreement.name)}
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

AgreementsIndex.layout = {
    breadcrumbs: [{ title: 'Agreements', href: BASE }],
};
