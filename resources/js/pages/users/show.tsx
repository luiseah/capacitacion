import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useDeleteUser } from '@/hooks/users/use-user-mutations';
import { useUser } from '@/hooks/users/use-user-queries';
import { edit, index } from '@/routes/users';
import { useTranslation } from '@/hooks/use-translation';

export default function UsersShow({ userId }: { userId: number }) {
    const { t, locale } = useTranslation();
    const { data: user, isPending, isError, error } = useUser(userId);
    const deletion = useDeleteUser();

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    const destroy = () =>
        deletion.mutate(user.id, {
            onSuccess: (response) => {
                toast.success(response.message ?? t('User deleted.'));
                router.visit(index());
            },
            onError: (failure) => toast.error(failure.message),
        });

    return (
        <>
            <Head title={user.full_name} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-xl font-semibold tracking-tight">{user.full_name}</h1>
                        <p className="text-sm text-muted-foreground">{user.email}</p>
                    </div>

                    <div className="flex shrink-0 gap-2">
                        {user.abilities.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(user.id)}>
                                    <Pencil /> {t('Edit')}
                                </Link>
                            </Button>
                        )}

                        {user.abilities.delete && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 /> {t('Delete')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{t('Delete user?')}</DialogTitle>
                                        <DialogDescription>
                                            {t('":name" will lose access. Users with requests cannot be deleted.', { name: user.full_name })}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                {t('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            disabled={deletion.isPending}
                                            onClick={destroy}
                                        >
                                            {t('Delete')}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Phone')}</dt>
                        <dd className="text-sm font-medium">{user.phone ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Roles')}</dt>
                        <dd className="flex flex-wrap gap-1 pt-1">
                            {user.roles.length > 0
                                ? user.roles.map((role) => (
                                      <Badge key={role} variant="secondary" className="capitalize">
                                          {t(role)}
                                      </Badge>
                                  ))
                                : '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Email verified')}</dt>
                        <dd className="text-sm font-medium">{user.email_verified ? t('Yes') : t('No')}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Created')}</dt>
                        <dd className="text-sm font-medium">
                            {new Date(user.created_at).toLocaleString(locale)}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}

UsersShow.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
