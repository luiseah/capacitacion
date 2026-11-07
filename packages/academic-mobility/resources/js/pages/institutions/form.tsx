import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { firstErrors } from '@/lib/request-payload';
import { ApiError } from '@/services';
import { useInstitution, useSaveInstitution } from '@academic/hooks/use-institutions';
import type { Institution } from '@academic/types';
import { useTranslation } from '@/hooks/use-translation';

const BASE = '/academic-mobility/institutions';

function InstitutionForm({ institution }: { institution?: Institution }) {
    const { t } = useTranslation();
    const save = useSaveInstitution(institution?.id);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isLocal, setIsLocal] = useState(institution?.is_local ?? false);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        const form = new FormData(event.currentTarget);

        save.mutate(
            {
                name: String(form.get('name') ?? '').trim(),
                country: String(form.get('country') ?? '').trim(),
                is_local: isLocal,
            },
            {
                onSuccess: (response) => {
                    toast.success(response.message ?? t('Institution saved.'));
                    router.visit(BASE);
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
            <Head title={institution ? t('Edit :name', { name: institution.name }) : t('New institution')} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={institution ? t('Edit institution') : t('New institution')}
                    description={t('The local institution is the one this application belongs to; mobilities are incoming or outgoing relative to it.')}
                />

                <form onSubmit={handleSubmit} className="w-full max-w-xl space-y-6">
                    <div className="grid gap-6 sm:grid-cols-[1fr_8rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')} *</Label>
                            <Input id="name" name="name" defaultValue={institution?.name} required />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="country">{t('Country')} *</Label>
                            <Input
                                id="country"
                                name="country"
                                defaultValue={institution?.country}
                                maxLength={2}
                                placeholder="ES"
                                className="uppercase"
                                required
                            />
                            <InputError message={errors.country} />
                        </div>
                    </div>
                    <p className="-mt-4 text-xs text-muted-foreground">
                        {t('Country as a 2-letter ISO code. Comparing it with the local institution decides whether a mobility is national or international.')}
                    </p>

                    <div className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_local"
                                checked={isLocal}
                                disabled={institution?.is_local}
                                onCheckedChange={(checked) => setIsLocal(checked === true)}
                            />
                            <Label htmlFor="is_local">{t('This is the local institution')}</Label>
                        </div>
                        {institution?.is_local ? (
                            <p className="text-xs text-muted-foreground">
                                {t('To change it, mark another institution as local.')}
                            </p>
                        ) : (
                            isLocal && (
                                <p className="text-xs text-muted-foreground">
                                    {t('The current local institution becomes a partner.')}
                                </p>
                            )
                        )}
                        <InputError message={errors.is_local} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={save.isPending}>
                            {t('Save')}
                        </Button>
                        <Button type="button" variant="secondary" asChild>
                            <Link href={BASE}>{t('Cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

export default function InstitutionsForm({ institutionId }: { institutionId?: string }) {
    const { t } = useTranslation();
    const { data: institution, isPending, isError, error } = useInstitution(institutionId);

    if (institutionId === undefined) {
        return <InstitutionForm />;
    }

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    return <InstitutionForm institution={institution} />;
}

InstitutionsForm.layout = {
    breadcrumbs: [
        { title: 'Institutions', href: BASE },
        { title: 'Form', href: BASE },
    ],
};
