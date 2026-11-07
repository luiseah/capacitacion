import { Dices } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { firstErrors } from '@/lib/request-payload';
import { ApiError } from '@/services';
import { useCreateParticipant, useSampleParticipant } from '@academic/hooks/use-mobilities';
import { PARTICIPANT_LABELS } from '@academic/types';
import type { Institution, ParticipantForm, ParticipantKind, ParticipantOption } from '@academic/types';

/**
 * Registers a visitor that is not in the list yet: the host describes the fields, the
 * package sends them with the origin institution the person comes from.
 */
export function VisitorFormDialog({
    open,
    onOpenChange,
    kind,
    institution,
    form,
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: ParticipantKind;
    institution: Institution;
    form: ParticipantForm;
    onCreated: (participant: ParticipantOption) => void;
}) {
    const { t } = useTranslation();
    const create = useCreateParticipant();
    const sample = useSampleParticipant();
    const [values, setValues] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Demos and development: invent a Spanish-named person of the institution, to review and register.
    const fillWithRandomData = () =>
        sample.mutate(
            { type: kind, institutionId: institution.id },
            {
                onSuccess: (response) => {
                    setErrors({});
                    setValues(response.data);
                },
                onError: (failure) => toast.error(failure.message),
            },
        );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // The dialog sits inside the mobility form: do not submit that one too.
        event.stopPropagation();
        setErrors({});

        create.mutate(
            {
                type: kind,
                direction: 'incoming',
                institution_id: institution.id,
                data: Object.fromEntries(Object.entries(values).filter(([, value]) => value.trim() !== '')),
            },
            {
                onSuccess: (response) => {
                    toast.success(response.message ?? t('Participant created.'));
                    setValues({});
                    onOpenChange(false);
                    onCreated(response.data);
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Register a visitor')}</DialogTitle>
                    <DialogDescription>
                        {t(':kind of :institution that is not in the list yet.', {
                            kind: t(PARTICIPANT_LABELS[kind]),
                            institution: institution.name,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {form.fields.map((field) => (
                            <div key={field.name} className="grid gap-2">
                                <Label htmlFor={`visitor-${field.name}`}>
                                    {field.label}
                                    {field.required ? ' *' : ''}
                                </Label>
                                <Input
                                    id={`visitor-${field.name}`}
                                    type={field.type ?? 'text'}
                                    value={values[field.name] ?? ''}
                                    onChange={(event) => setValues((current) => ({ ...current, [field.name]: event.target.value }))}
                                />
                                <InputError message={errors[`data.${field.name}`]} />
                            </div>
                        ))}
                    </div>
                    <InputError message={errors.institution_id ?? errors.type} />

                    <DialogFooter className="gap-2 sm:justify-between">
                        {form.can_sample ? (
                            <Button type="button" variant="outline" disabled={sample.isPending} onClick={fillWithRandomData}>
                                <Dices /> {t('Fill with random data')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={create.isPending}>
                                {t('Register')}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
