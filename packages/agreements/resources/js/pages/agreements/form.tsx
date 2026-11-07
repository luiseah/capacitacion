import { Head, Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { firstErrors } from '@/lib/request-payload';
import { ApiError } from '@/services';
import { StatusGuide } from '@agreements/components/agreement-list';
import { InstitutionPicker } from '@academic/components/institution-picker';
import { agreementDocumentUrl } from '@agreements/services/AgreementService';
import {
    useAgreement,
    useAgreements,
    useRemoveAgreementDocument,
    useSaveAgreement,
    useUploadAgreementDocument,
} from '@agreements/hooks/use-agreements';
import { AGREEMENT_ACTIVITIES, AGREEMENT_ACTIVITY_LABELS, AGREEMENT_RENEWAL_LABELS, AGREEMENT_STATUSES, AGREEMENT_STATUS_LABELS } from '@agreements/types';
import { enumLabel } from '@academic/types';
import type { Agreement, AgreementActivity, AgreementParties, AgreementRenewal, AgreementStatus, AgreementType } from '@agreements/types';
import type { Institution } from '@academic/types';

const BASE = '/agreements';
const textareaClass = 'min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm';
const selectClass = 'h-9 rounded-md border bg-background px-3 text-sm';

function AgreementForm({ agreement }: { agreement?: Agreement }) {
    const { t } = useTranslation();
    const save = useSaveAgreement(agreement?.id);
    const upload = useUploadAgreementDocument();
    const removeDocument = useRemoveAgreementDocument();
    const [file, setFile] = useState<File | null>(null);
    const [hasDocument, setHasDocument] = useState(agreement?.has_document ?? false);
    const [status, setStatus] = useState<AgreementStatus>(agreement?.recorded_status ?? 'draft');
    const [renewal, setRenewal] = useState<AgreementRenewal>(agreement?.renewal ?? 'manual');
    const [errors, setErrors] = useState<Record<string, string>>({});
    // Bilateral: the institution. Multilateral: all of them (two or more); the first one is the main one for the server.
    const [selected, setSelected] = useState<Institution[]>(
        agreement?.institution ? [agreement.institution, ...(agreement.institutions ?? [])] : [],
    );
    const [type, setType] = useState<AgreementType>(agreement?.type ?? 'specific');
    const [parties, setParties] = useState<AgreementParties>(agreement?.parties ?? 'bilateral');
    const [frameworkId, setFrameworkId] = useState(agreement?.framework_id ?? '');
    // The first question of a specific agreement: it can stand on its own or develop a framework one.
    const [linked, setLinked] = useState(Boolean(agreement?.framework_id));
    const [activities, setActivities] = useState<AgreementActivity[]>(agreement?.activities ?? []);

    const institution = selected.at(0) ?? null;

    // The framework agreements it can develop: those of the institution. Editing keeps the one it already has on the list.
    const { data: frameworks } = useAgreements({ institution_id: institution?.id, type: 'framework' });
    const frameworkOptions = [
        ...(frameworks?.items ?? []).filter((candidate) => candidate.id !== agreement?.id),
        ...(agreement?.framework && !(frameworks?.items ?? []).some((candidate) => candidate.id === agreement.framework?.id)
            ? [{ ...agreement.framework, institution: agreement.institution } as Agreement]
            : []),
    ];

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        const form = new FormData(event.currentTarget);
        const text = (name: string) => String(form.get(name) ?? '').trim();

        save.mutate(
            {
                institution_id: institution?.id ?? '',
                type,
                parties,
                institution_ids: parties === 'multilateral' ? selected.slice(1).map((other) => other.id) : [],
                framework_id: type === 'specific' && linked && frameworkId ? frameworkId : null,
                status,
                name: text('name'),
                reference: text('reference') || null,
                purpose: text('purpose') || null,
                signed_on: text('signed_on') || null,
                renewal,
                starts_on: text('starts_on'),
                ends_on: text('ends_on') || null,
                activities,
                other_activity: activities.includes('other') ? text('other_activity') || null : null,
                notes: text('notes') || null,
            },
            {
                onSuccess: (response) => {
                    const done = () => {
                        toast.success(response.message ?? t('Agreement saved.'));
                        router.visit(BASE);
                    };

                    // The signed document goes up once the agreement exists.
                    if (file === null) {
                        done();

                        return;
                    }

                    upload.mutate(
                        { id: response.data.id, file },
                        {
                            onSuccess: done,
                            onError: (failure) => {
                                // The agreement is saved: stay on its page to try the document again.
                                toast.error(failure.message);
                                router.visit(`${BASE}/${response.data.id}/edit`);
                            },
                        },
                    );
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
            <Head title={agreement ? t('Edit agreement') : t('New agreement')} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={agreement ? t('Edit agreement') : t('New agreement')}
                    description={t('An agreement between a partner institution and the local one. An institution can have as many as it needs.')}
                />

                <form onSubmit={handleSubmit} className="w-full max-w-2xl space-y-8">
                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('1 · Agreement')}</h2>

                        <div className="grid gap-2">
                            <Label>{t('Parties')} *</Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={parties}
                                onValueChange={(value) => {
                                    if (value) {
                                        setParties(value as AgreementParties);
                                        // A bilateral agreement is with one institution only.
                                        if (value === 'bilateral') {
                                            setSelected((current) => current.slice(0, 1));
                                        }
                                    }
                                }}
                                className="w-full items-stretch"
                                aria-label={t('Parties')}
                            >
                                <ToggleGroupItem value="bilateral" className="h-auto flex-1 flex-col items-start gap-1 px-4 py-3 text-left data-[state=on]:border-primary data-[state=on]:bg-primary/10">
                                    <span className="text-sm font-medium">{t('Bilateral')}</span>
                                    <span className="text-xs font-normal whitespace-normal text-muted-foreground">
                                        {t('The local institution and one more.')}
                                    </span>
                                </ToggleGroupItem>
                                <ToggleGroupItem value="multilateral" className="h-auto flex-1 flex-col items-start gap-1 px-4 py-3 text-left data-[state=on]:border-primary data-[state=on]:bg-primary/10">
                                    <span className="text-sm font-medium">{t('Multilateral')}</span>
                                    <span className="text-xs font-normal whitespace-normal text-muted-foreground">
                                        {t('The local institution and several others (a network or consortium).')}
                                    </span>
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <InputError message={errors.parties} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="institution">{parties === 'multilateral' ? t('Institutions') : t('Institution')} *</Label>

                            <InstitutionPicker
                                id="institution"
                                scope=""
                                anyScope
                                // Bilateral shows the chosen one; multilateral keeps adding to the list.
                                value={parties === 'multilateral' ? null : institution}
                                placeholder={parties === 'multilateral' ? t('Add an institution…') : undefined}
                                invalid={Boolean(errors.institution_id || errors.institution_ids)}
                                onChange={(chosen) => {
                                    if (parties === 'multilateral') {
                                        setSelected((current) => (current.some((candidate) => candidate.id === chosen.id) ? current : [...current, chosen]));
                                    } else {
                                        setSelected([chosen]);

                                        // The framework agreement belongs to the institution: choose it again.
                                        if (chosen.id !== institution?.id) {
                                            setFrameworkId('');
                                        }
                                    }
                                }}
                            />
                            {parties === 'multilateral' && (
                                <p className="text-xs text-muted-foreground">
                                    {t('Add every institution of the agreement: at least two.')}
                                </p>
                            )}

                            {parties === 'multilateral' && selected.length > 0 && (
                                <ul className="divide-y rounded-lg border">
                                    {selected.map((chosen, index) => (
                                        <li key={chosen.id} className="flex items-center gap-3 p-3 text-sm">
                                            <span className="w-6 text-right text-muted-foreground tabular-nums" aria-hidden>
                                                {index + 1}.
                                            </span>
                                            <span className="flex-1">
                                                <span className="font-medium">{chosen.name}</span>
                                                <span className="ml-2 text-xs text-muted-foreground">{chosen.country}</span>
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t('Remove :name', { name: chosen.name })}
                                                onClick={() => {
                                                    setSelected((current) => current.filter((candidate) => candidate.id !== chosen.id));
                                                    // The framework agreement is with the main (first) institution.
                                                    if (chosen.id === institution?.id) {
                                                        setFrameworkId('');
                                                    }
                                                }}
                                            >
                                                <X aria-hidden /> {t('Remove')}
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <InputError message={errors.institution_id} />
                            <InputError message={errors.institution_ids ?? errors['institution_ids.0'] ?? errors['institution_ids.1']} />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('Type')} *</Label>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={type}
                                onValueChange={(value) => {
                                    if (value) {
                                        setType(value as AgreementType);

                                        if (value === 'framework') {
                                            setLinked(false);
                                            setFrameworkId('');
                                        }
                                    }
                                }}
                                className="w-full items-stretch"
                                aria-label={t('Type')}
                            >
                                <ToggleGroupItem value="framework" className="h-auto flex-1 flex-col items-start gap-1 px-4 py-3 text-left data-[state=on]:border-primary data-[state=on]:bg-primary/10">
                                    <span className="text-sm font-medium">{t('Framework')}</span>
                                    <span className="text-xs font-normal whitespace-normal text-muted-foreground">
                                        {t('The general terms of the cooperation.')}
                                    </span>
                                </ToggleGroupItem>
                                <ToggleGroupItem value="specific" className="h-auto flex-1 flex-col items-start gap-1 px-4 py-3 text-left data-[state=on]:border-primary data-[state=on]:bg-primary/10">
                                    <span className="text-sm font-medium">{t('Specific')}</span>
                                    <span className="text-xs font-normal whitespace-normal text-muted-foreground">
                                        {t('A concrete activity, possibly under a framework agreement.')}
                                    </span>
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <InputError message={errors.type} />
                        </div>

                        {type === 'specific' && (
                            <div className="grid gap-3 rounded-lg border bg-muted/30 p-4">
                                <div className="grid gap-2">
                                    <Label>{t('Is it associated with a framework agreement?')}</Label>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        value={linked ? 'yes' : 'no'}
                                        onValueChange={(value) => {
                                            if (!value) {
                                                return;
                                            }

                                            setLinked(value === 'yes');

                                            if (value === 'no') {
                                                setFrameworkId('');
                                            }
                                        }}
                                        aria-label={t('Is it associated with a framework agreement?')}
                                    >
                                        <ToggleGroupItem value="no" className="px-6">
                                            {t('No')}
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="yes" className="px-6">
                                            {t('Yes')}
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                    <p className="text-xs text-muted-foreground">{t('It is optional: a specific agreement can stand on its own.')}</p>
                                </div>

                                {linked && (
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="framework_id">{t('Framework agreement')} *</Label>
                                        <select
                                            id="framework_id"
                                            value={frameworkId}
                                            onChange={(event) => setFrameworkId(event.target.value)}
                                            disabled={!institution}
                                            className={`${selectClass} w-full min-w-0 truncate`}
                                        >
                                            <option value="">{institution ? t('Choose a framework agreement…') : t('Choose the institution first')}</option>
                                            {frameworkOptions.map((framework) => (
                                                <option key={framework.id} value={framework.id}>
                                                    {framework.name}
                                                    {framework.reference ? ` (${framework.reference})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-muted-foreground">
                                            {t('The framework agreements of the institution above.')}
                                        </p>
                                        <InputError message={errors.framework_id} />
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="status">{t('Status')} *</Label>
                            <select
                                id="status"
                                value={status}
                                onChange={(event) => setStatus(event.target.value as AgreementStatus)}
                                className={`${selectClass} w-full sm:max-w-xs`}
                            >
                                {AGREEMENT_STATUSES.map((value) => (
                                    <option key={value} value={value}>
                                        {t(AGREEMENT_STATUS_LABELS[value])}
                                    </option>
                                ))}
                            </select>
                            <StatusGuide status={status} />
                            <InputError message={errors.status} />
                        </div>

                        <div className="grid items-start gap-4 sm:grid-cols-[1fr_12rem]">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="name">{t('Name')} *</Label>
                                <Input id="name" name="name" defaultValue={agreement?.name} required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor="reference">{t('Reference')}</Label>
                                <Input id="reference" name="reference" defaultValue={agreement?.reference ?? ''} placeholder="CONV-2026-001" />
                                <InputError message={errors.reference} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="purpose">{t('Purpose of the agreement')}</Label>
                            <textarea id="purpose" name="purpose" defaultValue={agreement?.purpose ?? ''} className={textareaClass} />
                            <p className="text-xs text-muted-foreground">{t('The formal purpose of the agreement.')}</p>
                            <InputError message={errors.purpose} />
                        </div>

                        {agreement && (
                            <p className="text-sm text-muted-foreground">
                                {t('Scope')}: <span className="font-medium text-foreground">{t(enumLabel(agreement.scope))}</span>{' '}
                                ({t('automatic, from the countries of its institutions')})
                            </p>
                        )}
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('2 · Validity')}</h2>

                        {/* One grid, every field on the same footing: label, control, then the hint. */}
                        <div className="grid items-start gap-x-4 gap-y-5 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="starts_on">{t('Starts on')} *</Label>
                                <Input id="starts_on" name="starts_on" type="date" defaultValue={agreement?.starts_on} required />
                                <p className="text-xs text-muted-foreground">{t('The day it comes into force.')}</p>
                                <InputError message={errors.starts_on} />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor="ends_on">{t('Ends on')}</Label>
                                <Input id="ends_on" name="ends_on" type="date" defaultValue={agreement?.ends_on ?? ''} />
                                <p className="text-xs text-muted-foreground">{t('Leave it empty for an indefinite agreement.')}</p>
                                <InputError message={errors.ends_on} />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor="signed_on">{t('Signing date')}</Label>
                                <Input id="signed_on" name="signed_on" type="date" defaultValue={agreement?.signed_on ?? ''} />
                                <p className="text-xs text-muted-foreground">{t('It does not have to match the start of the validity.')}</p>
                                <InputError message={errors.signed_on} />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor="renewal">{t('Renewal')}</Label>
                                <select id="renewal" value={renewal} onChange={(event) => setRenewal(event.target.value as AgreementRenewal)} className={`${selectClass} w-full`}>
                                    {(Object.keys(AGREEMENT_RENEWAL_LABELS) as AgreementRenewal[]).map((value) => (
                                        <option key={value} value={value}>
                                            {t(AGREEMENT_RENEWAL_LABELS[value])}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">{t('What happens when it reaches its end date.')}</p>
                                <InputError message={errors.renewal} />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('3 · Activities')}</h2>

                        <div className="grid gap-2">
                            <Label>{t('Activities')} *</Label>
                            <ToggleGroup
                                type="multiple"
                                variant="outline"
                                value={activities}
                                onValueChange={(value) => setActivities(value as AgreementActivity[])}
                                className="flex-wrap"
                                aria-label={t('Activities')}
                            >
                                {AGREEMENT_ACTIVITIES.map((activity) => (
                                    <ToggleGroupItem key={activity} value={activity} className="px-4 data-[state=on]:border-primary data-[state=on]:bg-primary/10">
                                        {t(AGREEMENT_ACTIVITY_LABELS[activity])}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                            <p className="text-xs text-muted-foreground">{t('It can cover several.')}</p>
                            <InputError message={errors.activities} />
                        </div>

                        {activities.includes('other') && (
                            <div className="grid gap-2 sm:max-w-md">
                                <Label htmlFor="other_activity">{t('Which other activity?')} *</Label>
                                <Input id="other_activity" name="other_activity" defaultValue={agreement?.other_activity ?? ''} />
                                <InputError message={errors.other_activity} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="document">{t('Signed document')}</Label>
                            {agreement && hasDocument && (
                                <div className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                                    <a href={agreementDocumentUrl(agreement.id)} target="_blank" rel="noreferrer" className="font-medium underline-offset-4 hover:underline">
                                        {agreement.document_name ?? t('Signed document')}
                                    </a>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={removeDocument.isPending}
                                        onClick={() =>
                                            removeDocument.mutate(agreement.id, {
                                                onSuccess: (response) => {
                                                    setHasDocument(false);
                                                    toast.success(response.message ?? t('Document removed.'));
                                                },
                                                onError: (failure) => toast.error(failure.message),
                                            })
                                        }
                                    >
                                        {t('Remove')}
                                    </Button>
                                </div>
                            )}
                            <Input id="document" type="file" accept="application/pdf,.pdf" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
                            <p className="text-xs text-muted-foreground">
                                {hasDocument ? t('Choosing a file replaces the current one.') : t('The PDF of the official agreement (up to 10 MB).')}
                            </p>
                            <InputError message={errors.document} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">{t('Notes')}</Label>
                            <textarea id="notes" name="notes" defaultValue={agreement?.notes ?? ''} className={textareaClass} />
                            <InputError message={errors.notes} />
                        </div>
                    </section>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={save.isPending || upload.isPending}>
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

export default function AgreementsForm({ agreementId }: { agreementId?: string }) {
    const { t } = useTranslation();
    const { data: agreement, isPending, isError, error } = useAgreement(agreementId);

    if (agreementId === undefined) {
        return <AgreementForm />;
    }

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    return <AgreementForm agreement={agreement} />;
}

AgreementsForm.layout = {
    breadcrumbs: [
        { title: 'Agreements', href: BASE },
        { title: 'Form', href: BASE },
    ],
};
