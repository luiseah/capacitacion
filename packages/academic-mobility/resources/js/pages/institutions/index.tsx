import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Institutions } from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';
import { useDeleteInstitution, useInstitutions } from '@academic/hooks/use-institutions';
import { useTranslation } from '@/hooks/use-translation';

const SEARCH_DELAY_MS = 300;
const BASE = '/academic-mobility/institutions';

export default function InstitutionsIndex() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const deletion = useDeleteInstitution();

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search.trim());
            setPage(1);
        }, SEARCH_DELAY_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const { data, isPending, isError, error, isPlaceholderData } = useInstitutions({
        page,
        search: debouncedSearch || undefined,
    });

    const meta = data?.meta;

    const destroy = (id: string, name: string) => {
        if (!window.confirm(t('Delete :name?', { name }))) {
            return;
        }

        deletion.mutate(id, {
            onSuccess: (response) => toast.success(response.message ?? t('Institution deleted.')),
            onError: (failure) => toast.error(failure.message),
        });
    };

    return (
        <>
            <Head title={t('Institutions')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">{t('Institutions')}</h1>
                    {can(Institutions.create) && (
                        <Button asChild>
                            <Link href={`${BASE}/create`}>{t('New institution')}</Link>
                        </Button>
                    )}
                </div>

                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t('Search by name')}
                    aria-label={t('Search institutions')}
                    className="sm:max-w-sm"
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">{t('Name')}</th>
                                <th className="p-3 font-medium">{t('Country')}</th>
                                <th className="p-3 font-medium">{t('Type')}</th>
                                <th className="p-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody className={isPlaceholderData ? 'opacity-60' : undefined}>
                            {isPending && (
                                <tr>
                                    <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                        {t('Loading…')}
                                    </td>
                                </tr>
                            )}
                            {isError && (
                                <tr>
                                    <td colSpan={4} className="p-6 text-center text-red-600 dark:text-red-400">
                                        {error.message}
                                    </td>
                                </tr>
                            )}
                            {data?.items.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                        {t('No institutions found.')}
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((institution) => (
                                <tr key={institution.id} className="border-t">
                                    <td className="p-3 font-medium">{institution.name}</td>
                                    <td className="p-3">{institution.country}</td>
                                    <td className="p-3">
                                        {institution.is_local ? (
                                            <Badge>{t('Local')}</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">{t('Partner')}</span>
                                        )}
                                    </td>
                                    <td className="flex justify-end gap-2 p-3">
                                        {can(Institutions.update) && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`${BASE}/${institution.id}/edit`}>{t('Edit')}</Link>
                                            </Button>
                                        )}
                                        {can(Institutions.destroy) && !institution.is_local && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={deletion.isPending}
                                                onClick={() => destroy(institution.id, institution.name)}
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

InstitutionsIndex.layout = {
    breadcrumbs: [{ title: 'Institutions', href: BASE }],
};
