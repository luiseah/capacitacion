import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Role } from '@/generated/permissions';
import { useUsers } from '@/hooks/users/use-user-queries';
import { index, show } from '@/routes/users';
import type { SortDirection, UserSortColumn } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

const SEARCH_DELAY_MS = 300;

export default function UsersIndex() {
    const { t, locale } = useTranslation();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [role, setRole] = useState('');
    const [sort, setSort] = useState<{ column: UserSortColumn; direction: SortDirection }>({
        column: 'name',
        direction: 'asc',
    });

    const toggleSort = (column: UserSortColumn) => {
        setSort((current) => ({
            column,
            direction: current.column === column && current.direction === 'asc' ? 'desc' : 'asc',
        }));
        setPage(1);
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search.trim());
            setPage(1);
        }, SEARCH_DELAY_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const { data, isPending, isError, error, isPlaceholderData } = useUsers({
        page,
        search: debouncedSearch || undefined,
        role: role || undefined,
        with: ['roles'],
        sort: { [sort.column]: sort.direction },
    });

    const meta = data?.meta;

    const arrow = (column: UserSortColumn) =>
        sort.column === column ? (sort.direction === 'asc' ? ' ↑' : ' ↓') : '';
    const ariaSort = (column: UserSortColumn) =>
        sort.column === column
            ? sort.direction === 'asc'
                ? ('ascending' as const)
                : ('descending' as const)
            : undefined;

    return (
        <>
            <Head title={t('Users')} />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">{t('Users')}</h1>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search by name, email or phone')}
                        aria-label={t('Search users')}
                        className="sm:max-w-sm"
                    />
                    <select
                        value={role}
                        onChange={(event) => {
                            setRole(event.target.value);
                            setPage(1);
                        }}
                        aria-label={t('Filter by role')}
                        className="h-9 rounded-md border bg-background px-3 text-sm capitalize"
                    >
                        <option value="">{t('All roles')}</option>
                        {Object.values(Role).map((value) => (
                            <option key={value} value={value}>
                                {t(value)}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('name')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('name')}
                                        className="font-medium"
                                    >
                                        {t('Name')}{arrow('name')}
                                    </button>
                                </th>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('lastname')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('lastname')}
                                        className="font-medium"
                                    >
                                        {t('Last name')}{arrow('lastname')}
                                    </button>
                                </th>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('email')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('email')}
                                        className="font-medium"
                                    >
                                        {t('Email')}{arrow('email')}
                                    </button>
                                </th>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('phone')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('phone')}
                                        className="font-medium"
                                    >
                                        {t('Phone')}{arrow('phone')}
                                    </button>
                                </th>
                                <th className="p-3 font-medium">{t('Roles')}</th>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('verified')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('verified')}
                                        className="font-medium"
                                    >
                                        {t('Verified')}{arrow('verified')}
                                    </button>
                                </th>
                                <th
                                    className="p-3 font-medium"
                                    aria-sort={ariaSort('created_at')}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort('created_at')}
                                        className="font-medium"
                                    >
                                        {t('Created')}{arrow('created_at')}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody className={isPlaceholderData ? 'opacity-60' : undefined}>
                            {isPending && (
                                <tr>
                                    <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                        {t('Loading…')}
                                    </td>
                                </tr>
                            )}
                            {isError && (
                                <tr>
                                    <td colSpan={7} className="p-6 text-center text-red-600 dark:text-red-400">
                                        {error.message}
                                    </td>
                                </tr>
                            )}
                            {data?.items.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                        {t('No users found.')}
                                    </td>
                                </tr>
                            )}
                            {data?.items.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="p-3">
                                        <Link
                                            href={show(user.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {user.name}
                                        </Link>
                                    </td>
                                    <td className="p-3">{user.lastname}</td>
                                    <td className="p-3">{user.email}</td>
                                    <td className="p-3">{user.phone ?? '—'}</td>
                                    <td className="p-3 capitalize">
                                        {user.roles.length > 0 ? user.roles.map((role) => t(role)).join(', ') : '—'}
                                    </td>
                                    <td className="p-3">{user.email_verified ? t('Yes') : t('No')}</td>
                                    <td className="p-3">
                                        {new Date(user.created_at).toLocaleDateString(locale)}
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

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
