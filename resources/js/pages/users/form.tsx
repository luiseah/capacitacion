import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Role } from '@/generated/permissions';
import { useUpdateUser } from '@/hooks/users/use-user-mutations';
import { useUser } from '@/hooks/users/use-user-queries';
import { firstErrors } from '@/lib/request-payload';
import { index, show } from '@/routes/users';
import { ApiError } from '@/services';
import type { ApiUser } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

function UserForm({ user }: { user: ApiUser }) {
    const { t } = useTranslation();
    const update = useUpdateUser();
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [roles, setRoles] = useState<string[]>(user.roles);

    const toggleRole = (role: string, checked: boolean) =>
        setRoles((current) =>
            checked ? [...current, role] : current.filter((value) => value !== role),
        );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        const form = new FormData(event.currentTarget);
        const text = (name: string) => String(form.get(name) ?? '');

        update.mutate(
            {
                id: user.id,
                payload: {
                    name: text('name'),
                    lastname: text('lastname'),
                    email: text('email'),
                    phone: text('phone') || null,
                    roles,
                },
            },
            {
                onSuccess: (response) => {
                    toast.success(response.message ?? t('User saved.'));
                    router.visit(show(user.id));
                },
                onError: (failure) => {
                    if (failure instanceof ApiError && failure.isValidation) {
                        setErrors(firstErrors(failure.errors));

                        return;
                    }

                    toast.error(failure.message);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Edit ${user.full_name}`} />

            <div className="flex flex-col gap-6 p-4">
                <Heading title={t('Edit user')} description={user.email} />

                <form onSubmit={handleSubmit} className="w-full max-w-xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')} *</Label>
                            <Input id="name" name="name" defaultValue={user.name} />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="lastname">{t('Last name')} *</Label>
                            <Input id="lastname" name="lastname" defaultValue={user.lastname} />
                            <InputError message={errors.lastname} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email')} *</Label>
                        <Input id="email" name="email" type="email" defaultValue={user.email} />
                        <p className="text-xs text-muted-foreground">
                            {t('Changing the email requires verifying it again.')}
                        </p>
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">{t('Phone')}</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            defaultValue={user.phone ?? ''}
                            placeholder="+34 600 000 000"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">{t('Roles')}</legend>
                        {Object.values(Role).map((role) => (
                            <div key={role} className="flex items-center gap-2">
                                <Checkbox
                                    id={`role-${role}`}
                                    checked={roles.includes(role)}
                                    onCheckedChange={(checked) => toggleRole(role, checked === true)}
                                />
                                <Label htmlFor={`role-${role}`} className="capitalize">
                                    {t(role)}
                                </Label>
                            </div>
                        ))}
                        <InputError message={errors.roles} />
                    </fieldset>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={update.isPending}>
                            {t('Save')}
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => router.visit(show(user.id))}>
                            {t('Cancel')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

export default function UsersForm({ userId }: { userId: number }) {
    const { t } = useTranslation();
    const { data: user, isPending, isError, error } = useUser(userId);

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    return <UserForm user={user} />;
}

UsersForm.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
